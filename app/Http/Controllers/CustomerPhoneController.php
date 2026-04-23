<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPhone;
use Illuminate\Http\Request;

class CustomerPhoneController extends Controller
{
    public function index(Customer $customer)
    {
        return response()->json(['data' => $customer->phones()->get()]);
    }

    public function store(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'phone'          => 'required|string|max:20',
            'label'          => 'nullable|string|max:30',
            'is_primary'     => 'nullable|boolean',
            'is_sms_capable' => 'nullable|boolean',
            'notes'          => 'nullable|string|max:255',
        ]);
        if ($request->boolean('is_primary')) {
            $customer->phones()->update(['is_primary' => false]);
        }
        $row = $customer->phones()->create([
            'phone'          => $data['phone'],
            'label'          => $data['label']          ?? null,
            'is_primary'     => $request->boolean('is_primary'),
            'is_sms_capable' => $request->boolean('is_sms_capable', true),
            'notes'          => $data['notes']          ?? null,
        ]);
        // Keep legacy customers.phone in sync with primary
        if ($row->is_primary) $customer->update(['phone' => $row->phone]);
        return back()->with('success', 'Phone added.');
    }

    public function update(Request $request, Customer $customer, CustomerPhone $phone)
    {
        abort_unless($phone->customer_id === $customer->id, 403);
        $data = $request->validate([
            'phone'          => 'sometimes|string|max:20',
            'label'          => 'sometimes|nullable|string|max:30',
            'is_primary'     => 'sometimes|boolean',
            'is_sms_capable' => 'sometimes|boolean',
            'notes'          => 'sometimes|nullable|string|max:255',
        ]);
        if (!empty($data['is_primary'])) {
            $customer->phones()->where('id', '!=', $phone->id)->update(['is_primary' => false]);
        }
        $phone->update($data);
        if ($phone->fresh()->is_primary) $customer->update(['phone' => $phone->phone]);
        return back()->with('success', 'Phone updated.');
    }

    public function destroy(Customer $customer, CustomerPhone $phone)
    {
        abort_unless($phone->customer_id === $customer->id, 403);
        $wasPrimary = $phone->is_primary;
        $phone->delete();
        if ($wasPrimary) {
            $next = $customer->phones()->first();
            if ($next) {
                $next->update(['is_primary' => true]);
                $customer->update(['phone' => $next->phone]);
            } else {
                $customer->update(['phone' => null]);
            }
        }
        return back()->with('success', 'Phone removed.');
    }
}
