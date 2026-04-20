<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationInspection extends Model
{
    protected $fillable = [
        'reservation_id', 'type', 'image_path', 'area',
        'notes', 'ai_analysis', 'has_damage', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['ai_analysis' => 'array', 'has_damage' => 'boolean'];
    }

    public const REQUIRED_AREAS = ['front', 'rear', 'left_side', 'right_side', 'interior', 'odometer'];

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function uploadedByUser(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    /**
     * Check which areas are still missing for a given reservation inspection type.
     */
    public static function getMissingAreas(int $reservationId, string $type): array
    {
        $captured = self::where('reservation_id', $reservationId)
            ->where('type', $type)
            ->pluck('area')
            ->toArray();

        return array_values(array_diff(self::REQUIRED_AREAS, $captured));
    }

    /**
     * Check if all required areas have been captured.
     */
    public static function isComplete(int $reservationId, string $type): bool
    {
        return empty(self::getMissingAreas($reservationId, $type));
    }
}
