<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Models\Location;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RentalService
{
    public function __construct(
        private readonly AccountingService $accounting,
    ) {}

    // ── Dashboard / Manifest ───────────────────────────
    public function getDailyManifest(?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : today();

        return [
            'date' => $date->toDateString(),
            'pickups' => Reservation::with(['customer', 'vehicle', 'pickupLocation'])
                ->whereDate('pickup_date', $date)
                ->whereIn('status', ['open', 'rental'])
                ->orderBy('pickup_date')
                ->get(),
            'returns' => Reservation::with(['customer', 'vehicle', 'returnLocation'])
                ->whereDate('return_date', $date)
                ->where('status', 'rental')
                ->orderBy('return_date')
                ->get(),
            'on_rent' => Reservation::where('status', 'rental')->count(),
            'overdue' => Reservation::where('status', 'rental')
                ->where('return_date', '<', now())
                ->count(),
        ];
    }

    public function getFleetUtilization(): array
    {
        $total = Vehicle::where('is_active', true)->count();
        $rented = Vehicle::where('status', 'rented')->count();
        $maintenance = Vehicle::where('status', 'maintenance')->count();
        $available = Vehicle::where('status', 'available')->where('is_active', true)->count();

        return [
            'total' => $total,
            'rented' => $rented,
            'available' => $available,
            'maintenance' => $maintenance,
            'utilization_pct' => $total > 0 ? round(($rented / $total) * 100, 1) : 0,
        ];
    }

    // ── Vehicles ───────────────────────────────────────
    public function getVehicles(?string $status = null, ?int $locationId = null): LengthAwarePaginator
    {
        return Vehicle::with(['location', 'activeReservation.customer'])
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->when($locationId, fn($q, $l) => $q->where('location_id', $l))
            ->where('is_active', true)
            ->orderBy('make')
            ->orderBy('model')
            ->paginate(25);
    }

    public function getAvailableVehicles(?string $class = null, ?int $locationId = null, ?string $pickupDate = null, ?string $returnDate = null): Collection
    {
        $query = Vehicle::available();

        if ($class) $query->byClass($class);
        if ($locationId) $query->atLocation($locationId);

        if ($pickupDate && $returnDate) {
            $bookedVehicleIds = Reservation::whereIn('status', ['open', 'rental'])
                ->where(function ($q) use ($pickupDate, $returnDate) {
                    $q->where(function ($q2) use ($pickupDate, $returnDate) {
                        $q2->where('pickup_date', '<=', $returnDate)
                            ->where('return_date', '>=', $pickupDate);
                    });
                })
                ->pluck('vehicle_id')
                ->filter();

            if ($bookedVehicleIds->isNotEmpty()) {
                $query->whereNotIn('id', $bookedVehicleIds);
            }
        }

        return $query->orderBy('vehicle_class')->orderBy('make')->get();
    }

    // ── Reservations ───────────────────────────────────
    public function getReservations(?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        return Reservation::with(['customer', 'vehicle', 'pickupLocation', 'returnLocation'])
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->when($search, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('reservation_number', 'ilike', "%{$search}%")
                       ->orWhereHas('customer', fn($q3) => $q3->where('first_name', 'ilike', "%{$search}%")
                           ->orWhere('last_name', 'ilike', "%{$search}%")
                           ->orWhere('phone', 'ilike', "%{$search}%"));
                });
            })
            ->orderByDesc('pickup_date')
            ->paginate(25);
    }

    public function createReservation(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $vehicle = isset($data['vehicle_id']) ? Vehicle::find($data['vehicle_id']) : null;

            $pickupDate = Carbon::parse($data['pickup_date']);
            $returnDate = Carbon::parse($data['return_date']);
            $totalDays = max(1, (int) $pickupDate->diffInDays($returnDate));

            $dailyRate = (float) ($data['daily_rate'] ?? $vehicle?->daily_rate ?? 0);
            $subtotal = $dailyRate * $totalDays;
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $totalPrice = $subtotal + $taxAmount - $discountAmount;

            $reservation = Reservation::create([
                'reservation_number' => Reservation::generateReservationNumber(),
                'customer_id' => $data['customer_id'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'vehicle_class' => $data['vehicle_class'] ?? $vehicle?->vehicle_class,
                'pickup_location_id' => $data['pickup_location_id'] ?? null,
                'return_location_id' => $data['return_location_id'] ?? $data['pickup_location_id'] ?? null,
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'daily_rate' => $dailyRate,
                'total_days' => $totalDays,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_price' => $totalPrice,
                'outstanding_balance' => $totalPrice,
                'security_deposit' => (float) ($data['security_deposit'] ?? 0),
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            return $reservation->load(['customer', 'vehicle', 'pickupLocation']);
        });
    }

    public function pickupVehicle(Reservation $reservation, array $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $data) {
            $reservation->update([
                'status' => 'rental',
                'actual_pickup_date' => now(),
                'vehicle_id' => $data['vehicle_id'] ?? $reservation->vehicle_id,
                'odometer_out' => $data['odometer_out'] ?? null,
                'fuel_out' => $data['fuel_out'] ?? null,
                'pickup_notes' => $data['pickup_notes'] ?? null,
            ]);

            if ($reservation->vehicle) {
                $reservation->vehicle->update([
                    'status' => 'rented',
                    'odometer' => $data['odometer_out'] ?? $reservation->vehicle->odometer,
                ]);
            }

            // Record revenue in accounting
            if ($reservation->total_price > 0) {
                $this->accounting->recordRentalRevenue(
                    (float) $reservation->total_price,
                    "Rental #{$reservation->reservation_number} - {$reservation->customer->full_name}",
                    auth()->id(),
                    Reservation::class,
                    $reservation->id,
                );
            }

            return $reservation->fresh(['customer', 'vehicle']);
        });
    }

    public function returnVehicle(Reservation $reservation, array $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $data) {
            $reservation->update([
                'status' => 'completed',
                'actual_return_date' => now(),
                'odometer_in' => $data['odometer_in'] ?? null,
                'fuel_in' => $data['fuel_in'] ?? null,
                'return_notes' => $data['return_notes'] ?? null,
            ]);

            if ($reservation->vehicle) {
                $reservation->vehicle->update([
                    'status' => 'available',
                    'odometer' => $data['odometer_in'] ?? $reservation->vehicle->odometer,
                    'fuel_level' => $data['fuel_in'] ?? $reservation->vehicle->fuel_level,
                    'location_id' => $reservation->return_location_id ?? $reservation->vehicle->location_id,
                ]);
            }

            $reservation->recalculateTotals();
            return $reservation->fresh(['customer', 'vehicle']);
        });
    }

    public function cancelReservation(Reservation $reservation): Reservation
    {
        $reservation->update(['status' => 'cancelled']);

        if ($reservation->vehicle && $reservation->vehicle->status === 'rented') {
            $reservation->vehicle->update(['status' => 'available']);
        }

        return $reservation;
    }

    // ── Payments ───────────────────────────────────────
    public function recordPayment(Reservation $reservation, array $data): void
    {
        DB::transaction(function () use ($reservation, $data) {
            $reservation->payments()->create([
                'customer_id' => $reservation->customer_id,
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'status' => 'approved',
                'type' => $data['type'] ?? 'payment',
                'sola_transaction_data' => $data['sola_transaction_data'] ?? null,
                'processed_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            $reservation->total_paid += (float) $data['amount'];
            $reservation->outstanding_balance = $reservation->total_price - $reservation->total_paid + $reservation->total_refunded;
            $reservation->save();

            // Record in accounting
            $this->accounting->recordRentalPaymentReceived(
                (float) $data['amount'],
                "Payment for Rental #{$reservation->reservation_number}",
                auth()->id(),
                Reservation::class,
                $reservation->id,
            );
        });
    }

    // ── Calendar Data ──────────────────────────────────
    public function getCalendarData(string $start, string $end, array $filters = []): Collection
    {
        $query = Reservation::with(['customer', 'vehicle', 'pickupLocation'])
            ->where('pickup_date', '<=', $end)
            ->where('return_date', '>=', $start);

        $statuses = $filters['statuses'] ?? ['open', 'rental', 'completed', 'pending', 'confirmed'];
        if (!empty($statuses)) $query->whereIn('status', $statuses);

        if (!empty($filters['location_ids'])) {
            $query->whereIn('pickup_location_id', $filters['location_ids']);
        }
        if (!empty($filters['vehicle_classes'])) {
            $query->whereIn('vehicle_class', $filters['vehicle_classes']);
        }
        if (!empty($filters['brands'])) {
            $query->whereHas('vehicle', fn($v) => $v->whereIn('make', $filters['brands']));
        }

        return $query->get()->map(fn(Reservation $r) => [
            'id'              => $r->id,
            'title'           => ($r->customer->full_name ?? 'Unknown') . ' - ' . ($r->vehicle?->display_name ?? $r->vehicle_class ?? 'No Vehicle'),
            'customer_name'   => $r->customer?->full_name,
            'start'           => $r->pickup_date->toIso8601String(),
            'end'             => $r->return_date->toIso8601String(),
            'status'          => $r->status,
            'location_id'     => $r->pickup_location_id,
            'location_name'   => $r->pickupLocation?->name,
            'vehicle_id'      => $r->vehicle_id,
            'vehicle_label'   => $r->vehicle?->display_name
                                 ?? ($r->vehicle_class ? "Unassigned ({$r->vehicle_class})" : 'Unassigned'),
            'vehicle_plate'   => $r->vehicle?->license_plate,
            'vehicle_class'   => $r->vehicle_class,
            'brand'           => $r->vehicle?->make,
            'color'           => match($r->status) {
                'open'      => '#3B82F6',
                'rental'    => '#10B981',
                'completed' => '#6B7280',
                default     => '#EF4444',
            },
        ]);
    }
}
