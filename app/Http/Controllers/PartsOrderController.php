<?php

namespace App\Http\Controllers;

use App\Models\PartsOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartsOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PartsOrder::with(['assignedToUser', 'claim', 'comments'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where('vehicle_description', 'ilike', "%{$s}%"));

        return Inertia::render('Parts/Index', [
            'pending' => (clone $query)->whereNotIn('status', ['out'])->orderByDesc('created_at')->get(),
            'out' => (clone $query)->where('status', 'out')->orderByDesc('updated_at')->get(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'pending' => PartsOrder::where('status', 'pending')->count(),
                'ordered' => PartsOrder::where('status', 'ordered')->count(),
                'received' => PartsOrder::where('status', 'received')->count(),
                'out' => PartsOrder::where('status', 'out')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_description' => 'required|string|max:255',
            'parts_list' => 'nullable|string',
            'vendor' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        PartsOrder::create(array_merge($validated, ['created_by' => auth()->id()]));

        return back()->with('success', 'Parts order added.');
    }

    public function updateStatus(Request $request, PartsOrder $partsOrder)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,ordered,received,installed,out',
        ]);

        $partsOrder->update($validated);
        if ($validated['status'] === 'received') $partsOrder->update(['received_date' => now()]);

        return back()->with('success', 'Status updated.');
    }

    public function addComment(Request $request, PartsOrder $partsOrder)
    {
        $validated = $request->validate(['body' => 'required|string']);
        $partsOrder->comments()->create(['body' => $validated['body'], 'user_id' => auth()->id()]);
        return back();
    }
}
