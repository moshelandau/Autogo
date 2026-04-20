<?php

namespace App\Http\Controllers;

use App\Models\BodyshopLift;
use App\Models\BodyshopSlot;
use App\Models\BodyshopWorker;
use App\Models\Claim;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BodyshopController extends Controller
{
    /**
     * Visual floor view: each lift is a card. Active slot shown inside.
     */
    public function floor()
    {
        $lifts = BodyshopLift::where('is_active', true)
            ->orderBy('position')->orderBy('name')
            ->with(['activeSlot.worker:id,name,color,role', 'activeSlot.customer:id,first_name,last_name', 'activeSlot.claim:id'])
            ->get();

        return Inertia::render('Bodyshop/Floor', [
            'lifts'   => $lifts,
            'workers' => BodyshopWorker::where('is_active', true)->orderBy('name')->get(),
            'phases'  => BodyshopSlot::PHASES,
            'recentClaims' => Claim::with('customer:id,first_name,last_name')
                ->whereIn('status', ['new','filed','in_progress'])
                ->latest()->limit(50)->get(['id','customer_id','vehicle_year','vehicle_make','vehicle_model','vehicle_plate','status']),
        ]);
    }

    public function workers()
    {
        return Inertia::render('Bodyshop/Workers', [
            'workers' => BodyshopWorker::orderByDesc('is_active')->orderBy('name')->get(),
            'roles'   => BodyshopWorker::ROLES,
        ]);
    }

    public function storeWorker(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'role'  => 'required|in:'.implode(',', BodyshopWorker::ROLES),
            'color' => 'nullable|string|max:20',
            'hourly_rate' => 'nullable|numeric|min:0',
            'hire_date'   => 'nullable|date',
            'is_active' => 'boolean',
        ]);
        BodyshopWorker::create($data + ['color' => $data['color'] ?? '#6366f1']);
        return back()->with('success', 'Worker added.');
    }

    public function updateWorker(Request $request, BodyshopWorker $worker)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'role'  => 'required|in:'.implode(',', BodyshopWorker::ROLES),
            'color' => 'nullable|string|max:20',
            'hourly_rate' => 'nullable|numeric|min:0',
            'hire_date'   => 'nullable|date',
            'is_active'   => 'boolean',
        ]);
        $worker->update($data);
        return back()->with('success','Worker updated.');
    }

    public function destroyWorker(BodyshopWorker $worker)
    {
        $worker->delete();
        return back()->with('success','Worker removed.');
    }

    public function lifts()
    {
        return Inertia::render('Bodyshop/Lifts', [
            'lifts' => BodyshopLift::orderBy('position')->orderBy('name')->get(),
            'types' => BodyshopLift::TYPES,
        ]);
    }

    public function storeLift(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'type'  => 'required|in:'.implode(',', BodyshopLift::TYPES),
            'position' => 'nullable|integer',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        BodyshopLift::create($data + ['color' => $data['color'] ?? '#0ea5e9']);
        return back()->with('success','Lift added.');
    }

    public function updateLift(Request $request, BodyshopLift $lift)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'type'  => 'required|in:'.implode(',', BodyshopLift::TYPES),
            'position' => 'nullable|integer',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $lift->update($data);
        return back()->with('success','Lift updated.');
    }

    public function destroyLift(BodyshopLift $lift)
    {
        $lift->delete();
        return back()->with('success','Lift removed.');
    }

    /** Assign a vehicle/worker to a lift (creates an active slot). */
    public function assign(Request $request, BodyshopLift $lift)
    {
        $data = $request->validate([
            'bodyshop_worker_id'   => 'nullable|exists:bodyshop_workers,id',
            'claim_id'             => 'nullable|exists:claims,id',
            'customer_id'          => 'nullable|exists:customers,id',
            'vehicle_label'        => 'nullable|string|max:255',
            'vehicle_plate'        => 'nullable|string|max:20',
            'repair_phase'         => 'nullable|in:'.implode(',', BodyshopSlot::PHASES),
            'estimated_completion' => 'nullable|date',
            'notes'                => 'nullable|string',
        ]);

        // Close any existing active slot on this lift first
        $lift->slots()->whereIn('status', ['in_progress','paused','scheduled'])
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $lift->slots()->create($data + [
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);
        return back()->with('success', 'Assigned to lift.');
    }

    /** Free up a lift (mark active slot completed). */
    public function release(BodyshopLift $lift)
    {
        $lift->slots()->whereIn('status', ['in_progress','paused','scheduled'])
            ->update(['status' => 'completed', 'completed_at' => now()]);
        return back()->with('success', 'Lift released.');
    }

    /** Update an active slot (phase, pause/resume, notes). */
    public function updateSlot(Request $request, BodyshopSlot $slot)
    {
        $data = $request->validate([
            'repair_phase' => 'nullable|in:'.implode(',', BodyshopSlot::PHASES),
            'status'       => 'nullable|in:scheduled,in_progress,paused,completed',
            'estimated_completion' => 'nullable|date',
            'notes'        => 'nullable|string',
        ]);
        if (($data['status'] ?? null) === 'paused'    && !$slot->paused_at)    $data['paused_at']    = now();
        if (($data['status'] ?? null) === 'completed' && !$slot->completed_at) $data['completed_at'] = now();
        $slot->update($data);
        return back()->with('success', 'Slot updated.');
    }
}
