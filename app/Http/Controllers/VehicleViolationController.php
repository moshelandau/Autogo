<?php

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Models\RentalPayment;
use App\Models\Reservation;
use App\Models\VehicleViolation;
use App\Services\SolaPaymentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VehicleViolationController extends Controller
{
    public function index(Request $request)
    {
        $violations = VehicleViolation::with(['vehicle:id,year,make,model,license_plate', 'customer:id,first_name,last_name,phone', 'reservation:id,reservation_number'])
            ->when($request->q, fn($q,$s) => $q->where(function ($w) use ($s) {
                $w->where('plate','ilike',"%{$s}%")
                  ->orWhere('summons_number','ilike',"%{$s}%")
                  ->orWhere('citation_number','ilike',"%{$s}%")
                  ->orWhere('location','ilike',"%{$s}%");
            }))
            ->when($request->type, fn($q,$t) => $q->where('type', $t))
            ->when($request->status, fn($q,$s) => $q->where('status', $s))
            ->when($request->jurisdiction, fn($q,$j) => $q->where('jurisdiction', $j))
            ->orderByDesc('issued_at')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'unbilled_count'  => VehicleViolation::whereIn('status', ['new','received'])->count(),
            'unbilled_total'  => (float) VehicleViolation::whereIn('status', ['new','received'])->sum('total_due'),
            'billed_count'    => VehicleViolation::where('status', 'renter_billed')->count(),
            'paid_count'      => VehicleViolation::where('status', 'paid_by_renter')->count(),
            'this_month'     => VehicleViolation::whereMonth('issued_at', now()->month)->count(),
        ];

        return Inertia::render('Violations/Index', [
            'violations' => $violations,
            'stats'      => $stats,
            'types'      => VehicleViolation::TYPES,
            'statuses'   => VehicleViolation::STATUSES,
            'filters'    => $request->only(['q','type','status','jurisdiction']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Violations/Create', [
            'types'    => VehicleViolation::TYPES,
            'statuses' => VehicleViolation::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plate'           => 'required|string|max:20',
            'plate_state'     => 'nullable|string|max:2',
            'type'            => 'required|in:'.implode(',', array_keys(VehicleViolation::TYPES)),
            'jurisdiction'    => 'nullable|string|max:5',
            'issuing_agency'  => 'nullable|string|max:100',
            'summons_number'  => 'nullable|string|max:50',
            'citation_number' => 'nullable|string|max:50',
            'issue_number'    => 'nullable|string|max:50',
            'issued_at'       => 'required|date',
            'due_date'        => 'nullable|date',
            'location'        => 'nullable|string|max:255',
            'borough_or_county' => 'nullable|string|max:100',
            'fine_amount'     => 'required|numeric|min:0',
            'late_fee'        => 'nullable|numeric|min:0',
            'admin_fee'       => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'photo'           => 'nullable|image|mimes:jpg,jpeg,png,heic,webp|max:15360',
            'document'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:15360',
        ]);

        // Default admin fee
        $data['admin_fee'] ??= 25.00;

        // Photo / doc
        if ($request->hasFile('photo'))    $data['photo_path']    = $request->file('photo')->store('violations', 'public');
        if ($request->hasFile('document')) $data['document_path'] = $request->file('document')->store('violations', 'public');

        $v = new VehicleViolation($data + ['created_by' => auth()->id(), 'status' => 'received']);
        $v->plate = strtoupper(preg_replace('/\s+/', '', $v->plate));
        $v->plate_state = $v->plate_state ? strtoupper($v->plate_state) : null;
        $v->autoLink();
        $v->recalcTotalDue();
        $v->save();

        $msg = "Violation logged.";
        if ($v->reservation_id) $msg .= " Auto-linked to rental #{$v->reservation->reservation_number}.";
        else                    $msg .= " ⚠ No matching rental — review manually.";

        return redirect()->route('violations.show', $v)->with('success', $msg);
    }

    public function show(VehicleViolation $violation)
    {
        $violation->load(['vehicle', 'customer', 'reservation.customer']);
        return Inertia::render('Violations/Show', [
            'violation' => $violation,
            'types'     => VehicleViolation::TYPES,
            'statuses'  => VehicleViolation::STATUSES,
        ]);
    }

    public function update(Request $request, VehicleViolation $violation)
    {
        $data = $request->validate([
            'fine_amount' => 'nullable|numeric|min:0',
            'late_fee'    => 'nullable|numeric|min:0',
            'admin_fee'   => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:'.implode(',', array_keys(VehicleViolation::STATUSES)),
            'notes'       => 'nullable|string',
            'reservation_id' => 'nullable|exists:reservations,id',
        ]);
        $violation->fill($data);
        $violation->recalcTotalDue();
        $violation->save();
        return back()->with('success', 'Violation updated.');
    }

    /** Bill the renter for this violation: charges card on file via Sola + logs comm. */
    public function billRenter(Request $request, VehicleViolation $violation, SolaPaymentsService $sola)
    {
        if (!$violation->reservation_id) {
            return back()->with('error', 'No rental linked — assign a rental first.');
        }

        $reservation = $violation->reservation()->with('customer','activeHold')->first();
        $hold = $reservation->activeHold;
        if (!$hold) {
            // Fall back: just bump the reservation outstanding balance
            $reservation->increment('outstanding_balance', (float) $violation->total_due);
            $violation->update(['status' => 'renter_billed']);
            return back()->with('success', 'Added to rental outstanding balance (no card on file to auto-charge).');
        }

        $result = $sola->charge(
            account: SolaPaymentsService::ACCOUNT_HIGH_RENTAL,
            card:   ['brand' => $hold->card_brand, 'last4' => $hold->card_last4, 'exp' => $hold->card_exp],
            amount: (float) $violation->total_due,
            description: "Violation pass-through — {$violation->summons_number} ({$violation->type})",
        );

        if (!($result['ok'] ?? false)) {
            return back()->with('error', 'Charge failed: ' . ($result['error'] ?? 'unknown'));
        }

        RentalPayment::create([
            'reservation_id' => $reservation->id,
            'customer_id'    => $reservation->customer_id,
            'payment_method' => 'card_on_file',
            'card_brand'     => $hold->card_brand,
            'card_last4'     => $hold->card_last4,
            'amount'         => $violation->total_due,
            'reference'      => "Violation {$violation->summons_number}",
            'status'         => 'completed',
            'type'           => 'violation',
            'sola_transaction_data' => json_encode($result),
            'processed_by'   => auth()->id(),
            'paid_at'        => now(),
        ]);

        $violation->update(['status' => 'paid_by_renter', 'paid_amount' => $violation->total_due]);
        $violation->recalcTotalDue();
        $violation->save();

        // Log communication
        if ($reservation->customer?->email) {
            CommunicationLog::create([
                'subject_type' => Reservation::class,
                'subject_id'   => $reservation->id,
                'customer_id'  => $reservation->customer_id,
                'user_id'      => auth()->id(),
                'channel'      => 'portal_message',
                'direction'    => 'outbound',
                'subject'      => 'Violation charge — '.$violation->summons_number,
                'body'         => "We charged \${$violation->total_due} to your card on file for the {$violation->type} violation issued on ".$violation->issued_at->format('m/d/Y').".",
                'status'       => 'sent',
                'sent_at'      => now(),
            ]);
        }

        return back()->with('success', "Charged $".number_format((float)$violation->total_due, 2)." to card on file.");
    }
}
