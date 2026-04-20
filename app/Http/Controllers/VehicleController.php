<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with(['location', 'activeReservation.customer'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->location_id, fn($q, $l) => $q->where('location_id', $l))
            ->when($request->vehicle_class, fn($q, $c) => $q->where('vehicle_class', $c))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('make', 'ilike', "%{$search}%")
                       ->orWhere('model', 'ilike', "%{$search}%")
                       ->orWhere('license_plate', 'ilike', "%{$search}%")
                       ->orWhere('vin', 'ilike', "%{$search}%");
                });
            })
            ->where('is_active', true)
            ->orderBy('make')->orderBy('model')
            ->paginate(25)->withQueryString();

        return Inertia::render('Rental/Vehicles/Index', [
            'vehicles' => $vehicles,
            'locations' => Location::where('is_active', true)->get(),
            'filters' => $request->only(['search', 'status', 'location_id', 'vehicle_class']),
            'stats' => [
                'total' => Vehicle::where('is_active', true)->count(),
                'available' => Vehicle::where('status', 'available')->where('is_active', true)->count(),
                'rented' => Vehicle::where('status', 'rented')->count(),
                'maintenance' => Vehicle::where('status', 'maintenance')->count(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Rental/Vehicles/Create', [
            'locations' => Location::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vin' => 'nullable|string|max:17|unique:vehicles,vin',
            'year' => 'required|integer|min:1990|max:2030',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'trim' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:20',
            'vehicle_class' => 'required|in:car,suv,minivan,truck',
            'location_id' => 'nullable|exists:locations,id',
            'odometer' => 'nullable|integer|min:0',
            'daily_rate' => 'required|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $vehicle = Vehicle::create($validated);

        return redirect()->route('rental.vehicles.show', $vehicle)
            ->with('success', 'Vehicle added to fleet.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['location', 'reservations' => fn($q) => $q->with('customer')->latest()->take(10), 'maintenances' => fn($q) => $q->latest()->take(5)]);

        return Inertia::render('Rental/Vehicles/Show', [
            'vehicle' => $vehicle,
        ]);
    }

    public function edit(Vehicle $vehicle)
    {
        return Inertia::render('Rental/Vehicles/Edit', [
            'vehicle' => $vehicle,
            'locations' => Location::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'vin' => "nullable|string|max:17|unique:vehicles,vin,{$vehicle->id}",
            'year' => 'required|integer|min:1990|max:2030',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'trim' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:20',
            'vehicle_class' => 'required|in:car,suv,minivan,truck',
            'status' => 'nullable|in:available,rented,maintenance,out_of_service,sold',
            'location_id' => 'nullable|exists:locations,id',
            'odometer' => 'nullable|integer|min:0',
            'fuel_level' => 'nullable|string',
            'daily_rate' => 'required|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $vehicle->update($validated);

        return redirect()->route('rental.vehicles.show', $vehicle)
            ->with('success', 'Vehicle updated.');
    }
}
