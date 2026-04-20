<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationInspection;
use Illuminate\Support\Collection;

class InspectionService
{
    /**
     * Upload an inspection image for a reservation.
     */
    public function addInspectionImage(Reservation $reservation, string $type, string $area, string $imagePath, ?string $notes = null): ReservationInspection
    {
        return ReservationInspection::create([
            'reservation_id' => $reservation->id,
            'type' => $type,
            'image_path' => $imagePath,
            'area' => $area,
            'notes' => $notes,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Get inspection status — which areas are covered vs missing.
     */
    public function getInspectionStatus(int $reservationId, string $type): array
    {
        $captured = ReservationInspection::where('reservation_id', $reservationId)
            ->where('type', $type)
            ->get();

        $missing = ReservationInspection::getMissingAreas($reservationId, $type);
        $isComplete = empty($missing);

        return [
            'is_complete' => $isComplete,
            'captured_areas' => $captured->pluck('area')->unique()->values()->toArray(),
            'missing_areas' => $missing,
            'images' => $captured,
            'total_required' => count(ReservationInspection::REQUIRED_AREAS),
            'total_captured' => $captured->pluck('area')->unique()->count(),
        ];
    }

    /**
     * Compare pickup vs return images to detect potential damage.
     * Returns areas where return images show potential new damage.
     */
    public function compareInspections(int $reservationId): array
    {
        $pickupImages = ReservationInspection::where('reservation_id', $reservationId)
            ->where('type', 'pickup')
            ->get()
            ->keyBy('area');

        $returnImages = ReservationInspection::where('reservation_id', $reservationId)
            ->where('type', 'return')
            ->get()
            ->keyBy('area');

        $comparison = [];
        foreach (ReservationInspection::REQUIRED_AREAS as $area) {
            $pickup = $pickupImages->get($area);
            $return = $returnImages->get($area);

            $comparison[] = [
                'area' => $area,
                'pickup_image' => $pickup?->image_path,
                'return_image' => $return?->image_path,
                'pickup_has_damage' => $pickup?->has_damage ?? false,
                'return_has_damage' => $return?->has_damage ?? false,
                'new_damage' => !($pickup?->has_damage ?? false) && ($return?->has_damage ?? false),
                'pickup_notes' => $pickup?->notes,
                'return_notes' => $return?->notes,
                'pickup_captured' => $pickup !== null,
                'return_captured' => $return !== null,
            ];
        }

        $newDamageAreas = collect($comparison)->where('new_damage', true)->pluck('area')->toArray();

        return [
            'comparison' => $comparison,
            'new_damage_detected' => !empty($newDamageAreas),
            'new_damage_areas' => $newDamageAreas,
            'pickup_complete' => ReservationInspection::isComplete($reservationId, 'pickup'),
            'return_complete' => ReservationInspection::isComplete($reservationId, 'return'),
        ];
    }

    /**
     * Flag an inspection image as having damage.
     */
    public function flagDamage(ReservationInspection $inspection, bool $hasDamage, ?string $notes = null): void
    {
        $data = ['has_damage' => $hasDamage];
        if ($notes !== null) $data['notes'] = $notes;
        $inspection->update($data);
    }
}
