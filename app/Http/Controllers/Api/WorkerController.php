<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationInspection;
use App\Models\User;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * JSON API for the AutoGo Worker Android app. Auth is via Sanctum
 * personal access tokens — workers sign in once on the phone, the token
 * is stored in DataStore, and every subsequent call carries it as a
 * Bearer header.
 */
class WorkerController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string|max:60',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'android-worker')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Today's pickups + returns. Workers see all active reservations —
     * any of them can need a pickup or return — sorted with most-urgent
     * first (today's pickups, then today's returns, then upcoming).
     */
    public function reservations(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $reservations = Reservation::query()
            ->with(['customer:id,first_name,last_name,phone', 'vehicle:id,make,model,year,license_plate'])
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where(function ($q) use ($today) {
                $q->whereDate('pickup_date', '<=', now()->addDays(2)->toDateString())
                  ->orWhereDate('return_date', '<=', now()->addDays(1)->toDateString());
            })
            ->orderBy('pickup_date')
            ->limit(100)
            ->get()
            ->map(function (Reservation $r) {
                return [
                    'id'                 => $r->id,
                    'reservation_number' => $r->reservation_number,
                    'status'             => $r->status,
                    'pickup_date'        => optional($r->pickup_date)->toIso8601String(),
                    'return_date'        => optional($r->return_date)->toIso8601String(),
                    'customer_name'      => trim(($r->customer?->first_name ?? '') . ' ' . ($r->customer?->last_name ?? '')) ?: null,
                    'customer_phone'     => $r->customer?->phone,
                    'vehicle_label'      => $r->vehicle ? trim("{$r->vehicle->year} {$r->vehicle->make} {$r->vehicle->model} ({$r->vehicle->license_plate})") : null,
                    'pickup_location'    => $r->pickup_location,
                    'return_location'    => $r->return_location,
                ];
            });

        return response()->json(['data' => $reservations]);
    }

    public function uploadInspection(Request $request, Reservation $reservation, InspectionService $inspections): JsonResponse
    {
        $data = $request->validate([
            'type'  => 'required|in:pickup,return',
            'area'  => 'required|in:' . implode(',', ReservationInspection::REQUIRED_AREAS),
            'image' => 'required|file|max:51200|mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp,image/gif,image/bmp',
            'notes' => 'nullable|string',
        ]);

        $path = $request->file('image')->store("inspections/{$reservation->id}/{$data['type']}", 'public');
        $inspections->addInspectionImage(
            $reservation,
            $data['type'],
            $data['area'],
            $path,
            $data['notes'] ?? null,
        );

        return response()->json(['ok' => true, 'message' => "{$data['area']} {$data['type']} photo saved."]);
    }
}
