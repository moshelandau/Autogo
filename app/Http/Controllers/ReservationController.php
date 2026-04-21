<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Location;
use App\Services\RentalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReservationController extends Controller
{
    public function __construct(private readonly RentalService $rental) {}

    public function index(Request $request)
    {
        return Inertia::render('Rental/Reservations/Index', [
            'reservations' => $this->rental->getReservations($request->status, $request->search),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Rental/Reservations/Create', [
            'customers' => Customer::where('is_active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'phone', 'email']),
            'vehicles' => Vehicle::available()->orderBy('vehicle_class')->orderBy('make')->get(),
            'locations' => Location::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'vehicle_class' => 'nullable|string',
            'pickup_location_id' => 'required|exists:locations,id',
            'return_location_id' => 'nullable|exists:locations,id',
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after:pickup_date',
            'daily_rate' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $reservation = $this->rental->createReservation($validated);

        return redirect()->route('rental.reservations.show', $reservation)
            ->with('success', 'Reservation created.');
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'customer', 'vehicle', 'pickupLocation', 'returnLocation',
            'addons', 'payments', 'externalCharges', 'createdByUser',
            'inspections.uploadedByUser',
        ]);

        return Inertia::render('Rental/Reservations/Show', [
            'reservation' => $reservation,
            'availableVehicles' => $reservation->status === 'open'
                ? Vehicle::available()->byClass($reservation->vehicle_class ?? '')->get()
                : [],
            'inspectionAreas' => \App\Models\ReservationInspection::REQUIRED_AREAS,
            'inspectionStatus' => [
                'pickup_missing' => \App\Models\ReservationInspection::getMissingAreas($reservation->id, 'pickup'),
                'return_missing' => \App\Models\ReservationInspection::getMissingAreas($reservation->id, 'return'),
            ],
        ]);
    }

    public function pickup(Request $request, Reservation $reservation, \App\Services\SolaPaymentsService $sola)
    {
        $validated = $request->validate([
            'vehicle_id'   => 'nullable|exists:vehicles,id',
            'odometer_out' => 'nullable|integer|min:0',
            'fuel_out'     => 'nullable|string',
            'pickup_notes' => 'nullable|string',
            'insurance_source'        => 'required|in:own_policy,credit_card,none',
            'insurance_company_seen'  => 'nullable|string',
            'insurance_policy_seen'   => 'nullable|string',
            'hold_amount'     => 'required|numeric|min:0|max:10000',
            'hold_card_brand' => 'nullable|string|max:20',
            'hold_card_last4' => 'nullable|string|size:4',
            'hold_card_exp'   => 'nullable|string|max:7',
        ]);

        // Persist insurance-at-pickup on the reservation
        $reservation->update([
            'insurance_source'         => $validated['insurance_source'],
            'insurance_company_seen'   => $validated['insurance_company_seen'] ?? null,
            'insurance_policy_seen'    => $validated['insurance_policy_seen']  ?? null,
        ]);

        // Authorize the $250 (or custom) hold on High Rental account
        if ($validated['hold_amount'] > 0 && !empty($validated['hold_card_last4'])) {
            $auth = $sola->authorizeHold(
                card: [
                    'last4' => $validated['hold_card_last4'],
                    'brand' => $validated['hold_card_brand'] ?? null,
                    'exp'   => $validated['hold_card_exp'] ?? null,
                ],
                amount: (float) $validated['hold_amount'],
                description: "Security deposit — rental #{$reservation->reservation_number}",
            );
            \App\Models\ReservationHold::create([
                'reservation_id'        => $reservation->id,
                'amount'                => $validated['hold_amount'],
                'card_brand'            => $validated['hold_card_brand'] ?? null,
                'card_last4'            => $validated['hold_card_last4'] ?? null,
                'card_exp'              => $validated['hold_card_exp'] ?? null,
                'sola_authorization_id' => $auth['authorization_id'] ?? null,
                'status'                => ($auth['ok'] ?? false) ? 'authorized' : 'failed',
                'placed_at'             => now(),
                'notes'                 => ($auth['mock'] ?? false) ? 'MOCK — Sola not yet configured' : null,
            ]);
        }

        $this->rental->pickupVehicle($reservation, \Illuminate\Support\Arr::only($validated,
            ['vehicle_id','odometer_out','fuel_out','pickup_notes']));

        return redirect()->route('rental.reservations.show', $reservation)
            ->with('success', 'Vehicle picked up. Rental is now active. Security deposit authorized.');
    }

    public function return(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'odometer_in' => 'nullable|integer|min:0',
            'fuel_in' => 'nullable|string',
            'return_notes' => 'nullable|string',
        ]);

        $this->rental->returnVehicle($reservation, $validated);

        return redirect()->route('rental.reservations.show', $reservation)
            ->with('success', 'Vehicle returned. Rental completed.');
    }

    public function cancel(Reservation $reservation)
    {
        $this->rental->cancelReservation($reservation);

        return redirect()->route('rental.reservations.show', $reservation)
            ->with('success', 'Reservation cancelled.');
    }

    public function recordPayment(Request $request, Reservation $reservation, \App\Services\SolaPaymentsService $sola)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card_on_file,new_card,credit_card,check,transfer',
            'amount'        => 'required|numeric|min:0.01',
            'tendered'      => 'nullable|numeric|min:0',
            'card_brand'    => 'nullable|string|max:20',
            'card_last4'    => 'nullable|string|size:4',
            'card_exp'      => 'nullable|string|max:7',
            'card_cvc'      => 'nullable|string|max:4',
            'sola_account'  => 'nullable|in:autogo,high_rental',
            'reference'     => 'nullable|string',
        ]);

        $method = $validated['payment_method'];
        $cardPayment = in_array($method, ['card_on_file','new_card','credit_card']);

        // For card payments, require a Sola account selection
        if ($cardPayment && empty($validated['sola_account'])) {
            return back()->withErrors(['sola_account' => 'Choose AutoGo or High Car Rental for card charges.']);
        }

        // Perform Sola charge if card
        $solaResult = null;
        if ($cardPayment) {
            // If card_on_file, reuse the active hold's card metadata
            $hold = $reservation->activeHold()->first();
            $card = [
                'brand' => $validated['card_brand'] ?? $hold?->card_brand,
                'last4' => $validated['card_last4'] ?? $hold?->card_last4,
                'exp'   => $validated['card_exp']   ?? $hold?->card_exp,
                'cvc'   => $validated['card_cvc']   ?? null,
            ];
            $solaResult = $sola->charge(
                account: $validated['sola_account'],
                card: $card,
                amount: (float) $validated['amount'],
                description: "Rental {$reservation->reservation_number}",
            );
            if (!($solaResult['ok'] ?? false)) {
                return back()->withErrors(['amount' => 'Payment failed: '.($solaResult['error'] ?? 'unknown')]);
            }
        }

        // Record the payment row
        \App\Models\RentalPayment::create([
            'reservation_id' => $reservation->id,
            'customer_id'    => $reservation->customer_id,
            'payment_method' => $method,
            'card_brand'     => $validated['card_brand'] ?? null,
            'card_last4'     => $validated['card_last4'] ?? null,
            'amount'         => $validated['amount'],
            'change_due'     => $method === 'cash' ? max(0, ($validated['tendered'] ?? 0) - $validated['amount']) : null,
            'reference'      => $validated['reference'] ?? ($solaResult['charge_id'] ?? null),
            'status'         => 'completed',
            'type'           => 'rental',
            'sola_transaction_data' => $solaResult ? json_encode($solaResult) : null,
            'processed_by'   => auth()->id(),
            'paid_at'        => now(),
        ]);

        return back()->with('success', "Payment recorded ({$method}, $".number_format((float)$validated['amount'],2).").");
    }

    /** Release or capture a security hold. */
    public function releaseHold(\App\Models\ReservationHold $hold, \App\Services\SolaPaymentsService $sola)
    {
        $sola->releaseHold($hold);
        return back()->with('success', 'Security hold released.');
    }

    public function captureHold(\App\Models\ReservationHold $hold, \App\Services\SolaPaymentsService $sola)
    {
        $sola->captureHold($hold);
        return back()->with('success', 'Hold captured as charge.');
    }

    /** After return, open a rental claim linked to the reservation. */
    public function openClaim(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'damage_description' => 'required|string',
            'priority'           => 'required|in:low,medium,high,urgent',
            'insurance_company'  => 'nullable|string',
            'insurance_claim_number' => 'nullable|string',
        ]);

        $claim = \App\Models\RentalClaim::create([
            'customer_id'       => $reservation->customer_id,
            'reservation_id'    => $reservation->id,
            'vehicle_id'        => $reservation->vehicle_id,
            'brand'             => 'high_rental',
            'status'            => 'new',
            'priority'          => $data['priority'],
            'damage_description'=> $data['damage_description'],
            'incident_date'     => $reservation->actual_return_date ?? now(),
            'insurance_company' => $data['insurance_company'] ?? null,
            'insurance_claim_number' => $data['insurance_claim_number'] ?? null,
            'created_by'        => auth()->id(),
        ]);
        return redirect()->route('rental-claims.show', $claim)->with('success', 'Rental claim opened. Upload damage photos below.');
    }

    public function calendar(Request $request)
    {
        $start = $request->start ?? now()->startOfMonth()->toDateString();
        $end   = $request->end   ?? now()->endOfMonth()->toDateString();

        $filters = [
            'location_ids'    => array_filter((array) $request->input('location_ids', [])),
            'vehicle_classes' => array_filter((array) $request->input('vehicle_classes', [])),
            'brands'          => array_filter((array) $request->input('brands', [])),
            'statuses'        => array_filter((array) $request->input('statuses', [])),
        ];

        return Inertia::render('Rental/Calendar', [
            'events'  => $this->rental->getCalendarData($start, $end, $filters),
            'start'   => $start,
            'end'     => $end,
            'filters' => $filters,
            'options' => [
                'locations' => \App\Models\Location::orderBy('name')->get(['id', 'name']),
                'vehicle_classes' => \App\Models\Reservation::query()
                    ->whereNotNull('vehicle_class')->where('vehicle_class', '!=', '')
                    ->distinct()->orderBy('vehicle_class')->pluck('vehicle_class')->values(),
                'brands' => \App\Models\Vehicle::query()
                    ->whereNotNull('make')->where('make', '!=', '')
                    ->distinct()->orderBy('make')->pluck('make')->values(),
                'statuses' => ['open', 'rental', 'confirmed', 'pending', 'completed', 'cancelled'],
            ],
            // Full active fleet (so calendar can compute available-vehicles for any day)
            'fleet' => \App\Models\Vehicle::where('is_active', true)
                ->orderBy('make')->orderBy('model')->orderBy('year')
                ->get(['id', 'year', 'make', 'model', 'trim', 'license_plate', 'vehicle_class'])
                ->map(fn($v) => [
                    'id'      => $v->id,
                    'label'   => trim("{$v->year} {$v->make} {$v->model}" . ($v->trim ? " {$v->trim}" : '')),
                    'plate'   => $v->license_plate,
                    'class'   => $v->vehicle_class,
                    'brand'   => $v->make,
                ]),
        ]);
    }
}
