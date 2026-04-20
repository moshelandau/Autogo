<?php

namespace App\Http\Controllers;

use App\Models\TowDriver;
use App\Models\TowJob;
use App\Models\TowTruck;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TowJobController extends Controller
{
    public function index(Request $request)
    {
        $jobs = TowJob::with(['customer:id,first_name,last_name,phone', 'truck:id,name', 'driver:id,name,phone'])
            ->when($request->status, fn($q,$s) => $q->where('status', $s))
            ->when($request->q, fn($q,$s) => $q->where(fn($w) =>
                $w->where('job_number','ilike',"%{$s}%")
                  ->orWhere('caller_name','ilike',"%{$s}%")
                  ->orWhere('vehicle_plate','ilike',"%{$s}%")
                  ->orWhere('reference_number','ilike',"%{$s}%")
            ))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'pending'    => TowJob::where('status','pending')->count(),
            'dispatched' => TowJob::where('status','dispatched')->count(),
            'on_scene'   => TowJob::where('status','on_scene')->count(),
            'completed_today' => TowJob::where('status','completed')->whereDate('completed_at', today())->count(),
        ];

        return Inertia::render('Towing/Index', compact('jobs','stats') + [
            'filters' => $request->only(['status','q']),
        ]);
    }

    /**
     * Live dispatch board (kanban by status).
     */
    public function board()
    {
        $statuses = ['pending','dispatched','en_route','on_scene','in_transit','completed'];
        $jobs = TowJob::with(['customer:id,first_name,last_name,phone','truck:id,name','driver:id,name'])
            ->whereIn('status', $statuses)
            ->whereDate('created_at','>=', now()->subDays(30))
            ->latest('id')->get();

        $columns = collect($statuses)->map(fn($s) => [
            'id' => $s,
            'label' => ucwords(str_replace('_',' ', $s)),
            'cards' => $jobs->where('status', $s)->values()->all(),
            'count' => $jobs->where('status', $s)->count(),
        ])->all();

        return Inertia::render('Towing/Board', [
            'columns' => $columns,
            'trucks'  => TowTruck::where('is_active',true)->orderBy('name')->get(['id','name']),
            'drivers' => TowDriver::where('is_active',true)->orderBy('name')->get(['id','name','phone']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Towing/Create', [
            'trucks'  => TowTruck::where('is_active',true)->orderBy('name')->get(['id','name']),
            'drivers' => TowDriver::where('is_active',true)->orderBy('name')->get(['id','name','phone']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'caller_name' => 'nullable|string|max:255',
            'caller_phone'=> 'nullable|string|max:20',
            'insurance_company' => 'nullable|string|max:255',
            'reference_number'  => 'nullable|string|max:80',
            'vehicle_year'  => 'nullable|string|max:4',
            'vehicle_make'  => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_plate' => 'nullable|string|max:20',
            'vehicle_vin'   => 'nullable|string|max:17',
            'vehicle_color' => 'nullable|string|max:30',
            'pickup_address' => 'required|string|max:255',
            'pickup_city'    => 'nullable|string|max:100',
            'pickup_state'   => 'nullable|string|max:2',
            'pickup_zip'     => 'nullable|string|max:10',
            'dropoff_address'=> 'required|string|max:255',
            'dropoff_city'   => 'nullable|string|max:100',
            'dropoff_state'  => 'nullable|string|max:2',
            'dropoff_zip'    => 'nullable|string|max:10',
            'reason'   => 'nullable|in:'.implode(',', TowJob::REASONS),
            'priority' => 'nullable|in:low,normal,high,urgent',
            'quoted_amount' => 'nullable|numeric|min:0',
            'tow_truck_id'  => 'nullable|exists:tow_trucks,id',
            'tow_driver_id' => 'nullable|exists:tow_drivers,id',
            'notes' => 'nullable|string',
        ]);

        $data['job_number'] = 'TOW-'.now()->format('ymd').'-'.str_pad((string)(TowJob::whereDate('created_at', today())->count()+1), 3, '0', STR_PAD_LEFT);
        $data['requested_at'] = now();
        $data['status'] = 'pending';
        $data['created_by'] = auth()->id();

        $job = TowJob::create($data);
        return redirect()->route('towing.show', $job)->with('success', "Tow job {$job->job_number} created.");
    }

    public function show(TowJob $towJob)
    {
        $towJob->load(['customer','claim','truck','driver','createdBy']);
        return Inertia::render('Towing/Show', [
            'job' => $towJob,
            'trucks'  => TowTruck::where('is_active',true)->get(['id','name']),
            'drivers' => TowDriver::where('is_active',true)->get(['id','name','phone']),
        ]);
    }

    public function update(Request $request, TowJob $towJob)
    {
        $data = $request->validate([
            'status' => 'nullable|in:'.implode(',', TowJob::STATUSES),
            'tow_truck_id'  => 'nullable|exists:tow_trucks,id',
            'tow_driver_id' => 'nullable|exists:tow_drivers,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'quoted_amount' => 'nullable|numeric|min:0',
            'billed_amount' => 'nullable|numeric|min:0',
            'paid_amount'   => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Auto-stamp transition timestamps
        if (($data['status'] ?? null) === 'dispatched' && !$towJob->dispatched_at) $data['dispatched_at'] = now();
        if (($data['status'] ?? null) === 'on_scene'   && !$towJob->on_scene_at)   $data['on_scene_at']   = now();
        if (($data['status'] ?? null) === 'completed'  && !$towJob->completed_at)  $data['completed_at']  = now();

        $towJob->update($data);
        return back()->with('success', 'Updated.');
    }

    /** Quick status change from board (POST). */
    public function setStatus(Request $request, TowJob $towJob)
    {
        $request->validate(['status' => 'required|in:'.implode(',', TowJob::STATUSES)]);
        return $this->update($request, $towJob);
    }

    public function destroy(TowJob $towJob)
    {
        $towJob->delete();
        return redirect()->route('towing.index')->with('success','Job deleted.');
    }
}
