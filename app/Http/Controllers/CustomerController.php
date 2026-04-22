<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->search($request->search)
            ->when($request->boolean('active_only', true), fn($q) => $q->where('is_active', true))
            ->orderBy('last_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'active_only']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'can_receive_sms' => 'boolean',
            'address' => 'nullable|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zip' => 'nullable|string|max:10',
            'drivers_license_number' => 'nullable|string|max:50',
            'dl_expiration' => 'nullable|date',
            'dl_state' => 'nullable|string|max:2',
            'date_of_birth' => 'nullable|date',
            'insurance_company' => 'nullable|string|max:255',
            'insurance_policy' => 'nullable|string|max:100',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Send the SMS bot intro to a customer's phone (or any number).
     * Manual trigger from the Customer Show page when staff wants to start
     * a lease or rental application without waiting for the customer to text in.
     */
    public function textApplication(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'flow'  => 'required|in:lease,rental,finance,towing,bodyshop',
            'phone' => 'nullable|string|max:20',
        ]);

        $phone = $validated['phone'] ?? $customer->phone;
        if (!$phone) return back()->with('error', 'No phone number provided and customer has none on file.');

        // Normalize to E.164-ish (digits only, prefix US "1" if 10 digits)
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) $digits = '1' . $digits;

        try {
            app(\App\Services\LeaseApplicationBot::class)->triggerManually($digits, $validated['flow'], $customer);
            return back()->with('success', "📱 {$validated['flow']} application sent to {$phone}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Send failed: ' . $e->getMessage());
        }
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'documents.uploadedBy',
            'deals',
            'reservations',
            'claims',
            'rentalClaims',
            'rentalPayments',
            'creditPulls',
        ]);

        // Build a unified, color-coded business-history timeline
        $history = collect();

        foreach ($customer->deals as $d) {
            $history->push([
                'kind'  => 'lease', 'icon' => '🚗', 'color' => 'indigo',
                'date'  => $d->created_at,
                'title' => "Lease/Finance Deal #{$d->deal_number}",
                'sub'   => trim("{$d->vehicle_year} {$d->vehicle_make} {$d->vehicle_model}"),
                'meta'  => $d->monthly_payment ? '$'.number_format((float)$d->monthly_payment, 2).'/mo' : null,
                'status'=> $d->stage,
                'href'  => route('leasing.deals.show', $d->id),
            ]);
        }

        foreach ($customer->reservations as $r) {
            $history->push([
                'kind'  => 'rental', 'icon' => '🔑', 'color' => 'sky',
                'date'  => $r->created_at,
                'title' => "Rental #{$r->reservation_number}",
                'sub'   => $r->pickup_date && $r->return_date
                    ? \Illuminate\Support\Carbon::parse($r->pickup_date)->format('M j') . ' → ' . \Illuminate\Support\Carbon::parse($r->return_date)->format('M j, Y')
                    : null,
                'meta'  => $r->total_price ? '$'.number_format((float)$r->total_price, 2) : null,
                'status'=> $r->status,
                'href'  => route('rental.reservations.show', $r->id),
            ]);
        }

        foreach ($customer->claims as $c) {
            $history->push([
                'kind'  => 'insurance_claim', 'icon' => '📋', 'color' => 'amber',
                'date'  => $c->created_at,
                'title' => "Insurance Claim #{$c->id}",
                'sub'   => trim("{$c->vehicle_year} {$c->vehicle_make} {$c->vehicle_model}") ?:
                    ($c->accident_date ? 'Loss ' . \Illuminate\Support\Carbon::parse($c->accident_date)->format('M j, Y') : null),
                'meta'  => $c->approved_amount ? '$'.number_format((float)$c->approved_amount, 2) : ($c->estimate_amount ? '$'.number_format((float)$c->estimate_amount, 2) : null),
                'status'=> $c->status,
                'href'  => route('claims.show', $c->id),
            ]);
        }

        foreach ($customer->rentalClaims as $rc) {
            $history->push([
                'kind'  => 'rental_claim', 'icon' => '⚠️', 'color' => 'rose',
                'date'  => $rc->created_at,
                'title' => "Rental Damage / Claim #{$rc->id}",
                'sub'   => $rc->incident_date ? 'Incident ' . \Illuminate\Support\Carbon::parse($rc->incident_date)->format('M j, Y') : ($rc->damage_description ?: null),
                'meta'  => $rc->damage_amount ? '$'.number_format((float)$rc->damage_amount, 2) : null,
                'status'=> $rc->status,
                'href'  => route('rental-claims.show', $rc->id),
            ]);
        }

        foreach ($customer->rentalPayments as $p) {
            $history->push([
                'kind'  => 'payment', 'icon' => '💵', 'color' => 'emerald',
                'date'  => $p->paid_at ?: $p->created_at,
                'title' => 'Payment received',
                'sub'   => $p->payment_method ? ucfirst($p->payment_method) : null,
                'meta'  => '$'.number_format((float)$p->amount, 2),
                'status'=> $p->status ?? 'paid',
                'href'  => null,
            ]);
        }

        foreach ($customer->creditPulls as $cp) {
            $history->push([
                'kind'  => 'credit_pull', 'icon' => '📊', 'color' => 'violet',
                'date'  => $cp->created_at,
                'title' => 'Credit pull (' . $cp->type . ')',
                'sub'   => $cp->bureau ? 'Bureau: ' . $cp->bureau : null,
                'meta'  => $cp->credit_score ? 'Score ' . $cp->credit_score : null,
                'status'=> null,
                'href'  => route('credit.show', $cp->id),
            ]);
        }

        $timeline = $history->sortByDesc('date')->values();

        $stats = [
            'deals_count'        => $customer->deals->count(),
            'reservations_count' => $customer->reservations->count(),
            'claims_count'       => $customer->claims->count() + $customer->rentalClaims->count(),
            'lifetime_revenue'   => round(
                (float) $customer->reservations->sum('total_price')
                + (float) $customer->rentalPayments->where('status', 'completed')->sum('amount')
                + (float) $customer->deals->sum('sell_price'), 2),
        ];

        return Inertia::render('Customers/Show', [
            'customer'      => $customer,
            'documentTypes' => \App\Models\CustomerDocument::TYPES,
            'timeline'      => $timeline,
            'stats'         => $stats,
        ]);
    }

    /**
     * Quick-create endpoint used by the inline CustomerSelect modal.
     * Returns the new customer as JSON so the dropdown can pick it immediately.
     */
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:255',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'state'       => 'nullable|string|max:2',
            'zip'         => 'nullable|string|max:10',
            'drivers_license_number' => 'nullable|string|max:50',
            'dl_state'    => 'nullable|string|max:2',
            'dl_expiration' => 'nullable|date',
            'date_of_birth' => 'nullable|date',
        ]);

        $customer = Customer::create(array_merge($validated, ['is_active' => true, 'can_receive_sms' => false]));

        return response()->json(['customer' => $customer]);
    }

    /**
     * JSON typeahead — returns up to 20 matches for use in searchable customer dropdowns.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $customers = Customer::query()
            ->where('is_active', true)
            ->search($q)
            ->orderBy('last_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'state', 'cached_outstanding_balance']);

        return response()->json([
            'data' => $customers->map(fn ($c) => [
                'id'    => $c->id,
                'label' => trim("{$c->first_name} {$c->last_name}"),
                'sub'   => trim(implode(' · ', array_filter([$c->phone, $c->email, $c->city ? "{$c->city}, {$c->state}" : null]))),
                'outstanding_balance' => (float) $c->cached_outstanding_balance,
            ]),
        ]);
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'can_receive_sms' => 'boolean',
            'address' => 'nullable|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zip' => 'nullable|string|max:10',
            'drivers_license_number' => 'nullable|string|max:50',
            'dl_expiration' => 'nullable|date',
            'dl_state' => 'nullable|string|max:2',
            'date_of_birth' => 'nullable|date',
            'insurance_company' => 'nullable|string|max:255',
            'insurance_policy' => 'nullable|string|max:100',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
}
