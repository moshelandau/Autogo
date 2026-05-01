<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationInspection;
use App\Services\InspectionService;
use App\Services\VehicleDamageAnalyzer;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    public function __construct(
        private readonly InspectionService $inspection,
        private readonly VehicleDamageAnalyzer $analyzer,
    ) {}

    public function upload(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'type' => 'required|in:pickup,return',
            'area' => 'required|in:' . implode(',', ReservationInspection::REQUIRED_AREAS),
            // Laravel's `image` rule rejects HEIC (iPhone default) — and
            // the 10 MB cap killed any high-res phone photo. Match the
            // license-upload pattern: explicit mimetypes + 50 MB cap so a
            // raw iPhone photo always saves on the first try.
            'image' => 'required|file|max:51200|mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp,image/gif,image/bmp',
            'notes' => 'nullable|string',
        ]);

        $path = $request->file('image')->store("inspections/{$reservation->id}/{$validated['type']}", 'public');

        $this->inspection->addInspectionImage(
            $reservation,
            $validated['type'],
            $validated['area'],
            $path,
            $validated['notes'] ?? null,
        );

        return back()->with('success', "{$validated['area']} photo uploaded.");
    }

    public function destroy(Reservation $reservation, ReservationInspection $inspection)
    {
        abort_unless($inspection->reservation_id === $reservation->id, 404);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($inspection->image_path);
        $inspection->delete();
        return back()->with('success', 'Photo removed.');
    }

    public function status(Reservation $reservation, string $type)
    {
        return response()->json(
            $this->inspection->getInspectionStatus($reservation->id, $type)
        );
    }

    public function compare(Reservation $reservation)
    {
        return response()->json(
            $this->inspection->compareInspections($reservation->id)
        );
    }

    public function flagDamage(Request $request, ReservationInspection $inspection)
    {
        $validated = $request->validate([
            'has_damage' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        $this->inspection->flagDamage($inspection, $validated['has_damage'], $validated['notes'] ?? null);

        return back()->with('success', 'Damage flag updated.');
    }

    /**
     * AI auto-analyze a single inspection image for damage.
     */
    public function analyzeImage(ReservationInspection $inspection)
    {
        $result = $this->analyzer->analyzeImage($inspection);
        return response()->json($result);
    }

    /**
     * AI auto-analyze ALL return images for a reservation, comparing with pickup.
     */
    public function analyzeReservation(Reservation $reservation)
    {
        $result = $this->analyzer->analyzeReservation($reservation->id);
        return response()->json($result);
    }
}
