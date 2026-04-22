<?php

namespace App\Http\Controllers;

use App\Models\EzPassAccount;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EzPassController extends Controller
{
    public function index(Request $request)
    {
        $accounts = EzPassAccount::with('customer')
            ->when($request->search, function ($q, $s) {
                $q->whereHas('customer', fn($q2) => $q2->search($s))
                   ->orWhere('account_number', 'ilike', "%{$s}%")
                   ->orWhere('tag_number', 'ilike', "%{$s}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50)->withQueryString();

        return Inertia::render('EzPass/Index', [
            'accounts' => $accounts,
            'customers' => Customer::where('is_active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'account_number' => 'nullable|string|max:50',
            'tag_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        EzPassAccount::create($validated);

        return back()->with('success', 'EZ Pass account added.');
    }

    public function update(Request $request, EzPassAccount $ezPassAccount)
    {
        $validated = $request->validate([
            'account_number' => 'nullable|string|max:50',
            'tag_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,suspended',
            'balance' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $ezPassAccount->update($validated);

        return back()->with('success', 'Account updated.');
    }
}
