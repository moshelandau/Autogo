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

    public function pickup(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'odometer_out' => 'nullable|integer|min:0',
            'fuel_out' => 'nullable|string',
            'pickup_notes' => 'nullable|string',
        ]);

        $this->rental->pickupVehicle($reservation, $validated);

        return redirect()->route('rental.reservations.show', $reservation)
            ->with('success', 'Vehicle picked up. Rental is now active.');
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

    public function recordPayment(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:credit_card,cash,check,transfer',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string',
        ]);

        $this->rental->recordPayment($reservation, $validated);

        return back()->with('success', 'Payment recorded.');
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
