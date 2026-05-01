<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerInsurancePolicy;
use App\Models\Reservation;
use App\Models\ReservationAdditionalDriver;
use App\Models\ReservationHold;
use App\Models\ReservationInspection;
use App\Models\Signature;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverLicenseAnalyzer;
use App\Services\InspectionService;
use App\Services\InsuranceCardAnalyzer;
use App\Services\RentalService;
use App\Services\SolaPaymentsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * JSON API for the AutoGo Worker Android app. Auth is via Sanctum
 * personal access tokens — workers sign in once on the phone, the token
 * is stored in DataStore, and every subsequent call carries it as a
 * Bearer header.
 *
 * The endpoints below mirror the on-lot rental workflow: pull up the
 * reservation, scan/verify driver's license, scan/verify insurance,
 * add additional drivers, swap vehicle if needed, capture inspection
 * photos, take security-deposit hold, capture customer signature, and
 * finalize pickup → status `rental`. Return is the mirror.
 *
 * Cardknox iFields PCI-safe entry is NOT INTEGRATED YET on either
 * platform — both web and mobile capture worker-typed brand/last4/exp
 * without a real PAN, and authorize the hold against that surface.
 * Treat as parity with web until iFields lands.
 */
class WorkerController extends Controller
{
    // ── Auth ───────────────────────────────────────────────────────

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

    // ── Reservation list + detail ──────────────────────────────────

    /**
     * Today's pickups + returns — workers see all active reservations,
     * sorted with most-urgent first (today's pickups, then today's returns,
     * then upcoming).
     */
    public function reservations(Request $request): JsonResponse
    {
        // The previous filter (pickup_date <= now+2 OR return_date <= now+1) had no
        // lower bound, which surfaced every "open" reservation from prior years —
        // workers got dozens of stale rentals from 2023 in their list. Bracket
        // BOTH ends of the window to "yesterday → 2 days out" so we only show
        // pickups + returns the worker can actually act on today.
        $start = now()->startOfDay()->subDay();
        $end   = now()->endOfDay()->addDays(2);

        $reservations = Reservation::query()
            ->with(['customer:id,first_name,last_name,phone', 'vehicle:id,make,model,year,license_plate'])
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('pickup_date', [$start, $end])
                  ->orWhereBetween('return_date', [$start, $end]);
            })
            ->orderBy('pickup_date')
            ->limit(100)
            ->get()
            ->map(fn (Reservation $r) => $this->reservationListItem($r));

        return response()->json(['data' => $reservations]);
    }

    /**
     * Reservation detail with the full pickup/return checklist state.
     * The phone uses this to render the work-list of what's still missing.
     */
    public function show(Reservation $reservation): JsonResponse
    {
        $reservation->load([
            'customer', 'vehicle', 'pickupLocation', 'returnLocation',
            'inspections:id,reservation_id,type,area,image_path',
            'additionalDrivers',
            'activeHold',
        ]);

        $insurance = $reservation->customer?->currentInsurance();

        return response()->json([
            'id'                 => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'status'             => $reservation->status,
            'pickup_date'        => optional($reservation->pickup_date)->toIso8601String(),
            'return_date'        => optional($reservation->return_date)->toIso8601String(),
            'odometer_out'       => $reservation->odometer_out,
            'odometer_in'        => $reservation->odometer_in,
            'fuel_out'           => $reservation->fuel_out,
            'fuel_in'            => $reservation->fuel_in,
            'total_price'        => (float) $reservation->total_price,
            'security_deposit'   => (float) $reservation->security_deposit,
            'outstanding_balance'=> (float) $reservation->outstanding_balance,
            'customer'           => $reservation->customer ? [
                'id'                       => $reservation->customer->id,
                'name'                     => trim($reservation->customer->first_name.' '.$reservation->customer->last_name),
                'phone'                    => $reservation->customer->phone,
                'email'                    => $reservation->customer->email,
                'drivers_license_number'   => $reservation->customer->drivers_license_number,
                'dl_state'                 => $reservation->customer->dl_state,
                'dl_expiration'            => optional($reservation->customer->dl_expiration)->toDateString(),
                'dl_front_image_url'       => $this->publicUrl($reservation->customer->dl_front_image_path),
                'dl_back_image_url'        => $this->publicUrl($reservation->customer->dl_back_image_path),
                'dl_verified_at'           => optional($reservation->customer->dl_verified_at)->toIso8601String(),
            ] : null,
            'vehicle'            => $reservation->vehicle ? [
                'id'            => $reservation->vehicle->id,
                'label'         => trim("{$reservation->vehicle->year} {$reservation->vehicle->make} {$reservation->vehicle->model} ({$reservation->vehicle->license_plate})"),
                'license_plate' => $reservation->vehicle->license_plate,
                'vehicle_class' => $reservation->vehicle->vehicle_class,
            ] : null,
            'insurance'          => $insurance ? [
                'id'              => $insurance->id,
                'carrier'         => $insurance->carrier,
                'policy_number'   => $insurance->policy_number,
                'named_insured'   => $insurance->named_insured,
                'expiration_date' => optional($insurance->expiration_date)->toDateString(),
                'image_url'       => $this->publicUrl($insurance->image_path),
                'verified_at'     => optional($insurance->verified_at)->toIso8601String(),
                'is_expired'      => $insurance->isExpired(),
            ] : null,
            'additional_drivers' => $reservation->additionalDrivers->map(fn ($d) => [
                'id'             => $d->id,
                'name'           => $d->name,
                'phone'          => $d->phone,
                'dl_number'      => $d->dl_number,
                'dl_state'       => $d->dl_state,
                'dl_expiration'  => optional($d->dl_expiration)->toDateString(),
                'dl_image_url'   => $this->publicUrl($d->dl_image_path),
            ])->values(),
            'required_areas'     => ReservationInspection::REQUIRED_AREAS,
            'inspection_state'   => $this->buildInspectionState($reservation),
            'hold'               => $reservation->activeHold ? [
                'id'         => $reservation->activeHold->id,
                'amount'     => (float) $reservation->activeHold->amount,
                'card_brand' => $reservation->activeHold->card_brand,
                'card_last4' => $reservation->activeHold->card_last4,
                'status'     => $reservation->activeHold->status,
            ] : null,
            'has_signature'      => Signature::where('signable_type', Reservation::class)
                                              ->where('signable_id', $reservation->id)->exists(),
        ]);
    }

    // ── Vehicle swap ───────────────────────────────────────────────

    /**
     * Vehicles the worker can swap to / pick up first.
     *
     * Shows every available car on the lot, not just the booking's class —
     * the worker sees real-world availability and can hand over whatever's
     * actually sitting outside. Same-class vehicles surface FIRST so the
     * worker's natural pick is right at the top, then other classes follow
     * for cases where the booked class has nothing on the lot.
     */
    public function swapOptions(Reservation $reservation): JsonResponse
    {
        $bookedClass = $reservation->vehicle?->vehicle_class ?? $reservation->vehicle_class;

        $vehicles = Vehicle::query()
            ->where('is_active', true)
            ->where('status', 'available')
            ->where(function ($q) use ($reservation) {
                // Don't show the currently-assigned vehicle in the swap list — the
                // worker is here to pick a DIFFERENT one. (Pre-pickup the assigned
                // vehicle is still in 'available' status; this filter hides it.)
                if ($reservation->vehicle_id) {
                    $q->where('id', '!=', $reservation->vehicle_id);
                }
            })
            ->limit(200)
            ->get(['id', 'year', 'make', 'model', 'license_plate', 'vehicle_class', 'odometer'])
            // Sort: same class first (preserve booked-class as group #0), then
            // alphabetical by class, then by make/model within each group.
            ->sortBy(function ($v) use ($bookedClass) {
                $classSort = ($bookedClass && $v->vehicle_class === $bookedClass) ? '0' : '1_'.($v->vehicle_class ?? 'zzz');
                return $classSort.'|'.($v->make ?? '').'|'.($v->model ?? '');
            })
            ->values()
            ->map(fn ($v) => [
                'id'                => $v->id,
                'label'             => trim("{$v->year} {$v->make} {$v->model} ({$v->license_plate})"),
                'license_plate'     => $v->license_plate,
                'vehicle_class'     => $v->vehicle_class,
                'odometer'          => $v->odometer,
                'is_booked_class'   => $bookedClass && $v->vehicle_class === $bookedClass,
            ]);

        return response()->json(['data' => $vehicles, 'booked_class' => $bookedClass]);
    }

    public function swapVehicle(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
        ]);

        if ($reservation->status !== 'open') {
            throw ValidationException::withMessages(['vehicle_id' => ['Vehicle can only be swapped while the reservation is open.']]);
        }

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        if ($vehicle->status !== 'available') {
            throw ValidationException::withMessages(['vehicle_id' => ['That vehicle is not available.']]);
        }

        $reservation->update([
            'vehicle_id'    => $vehicle->id,
            'vehicle_class' => $vehicle->vehicle_class,
        ]);

        return response()->json(['ok' => true, 'vehicle' => [
            'id'    => $vehicle->id,
            'label' => trim("{$vehicle->year} {$vehicle->make} {$vehicle->model} ({$vehicle->license_plate})"),
        ]]);
    }

    // ── Driver's license capture + OCR ─────────────────────────────

    public function uploadDriverLicense(Request $request, Customer $customer, DriverLicenseAnalyzer $ocr): JsonResponse
    {
        $request->validate([
            'front' => 'required|file|max:51200|mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp',
            'back'  => 'nullable|file|max:51200|mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp',
        ]);

        $frontPath = $request->file('front')->store("dl/{$customer->id}", 'public');
        $backPath  = $request->hasFile('back')
            ? $request->file('back')->store("dl/{$customer->id}", 'public')
            : null;

        $result = $ocr->extract($frontPath, $backPath);

        $update = [
            'dl_front_image_path' => $frontPath,
            'dl_back_image_path'  => $backPath,
        ];
        if ($result['success']) {
            $f = $result['fields'];
            $update['dl_ocr']         = $f;
            $update['dl_verified_at'] = now();
            // Backfill structured columns only when blank — never overwrite a
            // hand-entered value with an OCR guess.
            if (empty($customer->drivers_license_number) && !empty($f['license_number']))  $update['drivers_license_number'] = $f['license_number'];
            if (empty($customer->dl_state)               && !empty($f['state']))           $update['dl_state']               = $f['state'];
            if (empty($customer->dl_expiration)          && !empty($f['expiration_date'])) $update['dl_expiration']          = $f['expiration_date'];
            if (empty($customer->date_of_birth)          && !empty($f['date_of_birth']))   $update['date_of_birth']          = $f['date_of_birth'];
        }
        $customer->update($update);

        $fresh = $customer->fresh();
        return response()->json([
            'ok'     => true,
            'ocr'    => $result,
            'images' => [
                'front' => $this->publicUrl($frontPath),
                'back'  => $this->publicUrl($backPath),
            ],
            'customer' => [
                'drivers_license_number' => $fresh->drivers_license_number,
                'dl_state'               => $fresh->dl_state,
                'dl_expiration'          => optional($fresh->dl_expiration)->toDateString(),
                'date_of_birth'          => optional($fresh->date_of_birth)->toDateString(),
            ],
        ]);
    }

    // ── Insurance card capture + OCR ───────────────────────────────

    public function uploadInsurance(Request $request, Customer $customer, InsuranceCardAnalyzer $ocr): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|max:51200|mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp',
        ]);

        $path = $request->file('image')->store("insurance/{$customer->id}", 'public');
        $result = $ocr->extract($path);

        $f = $result['success'] ? $result['fields'] : [];

        $policy = DB::transaction(function () use ($customer, $path, $result, $f) {
            // Mark previous policies non-current — only one active per customer.
            $customer->insurancePolicies()->update(['is_current' => false]);

            return CustomerInsurancePolicy::create([
                'customer_id'     => $customer->id,
                'carrier'         => $f['carrier']         ?? null,
                'policy_number'   => $f['policy_number']   ?? null,
                'naic'            => $f['naic']            ?? null,
                'named_insured'   => $f['named_insured']   ?? null,
                'effective_date'  => $f['effective_date']  ?? null,
                'expiration_date' => $f['expiration_date'] ?? null,
                'image_path'      => $path,
                'ocr'             => $result['success'] ? $f : null,
                'verified_at'     => $result['success'] ? now() : null,
                'verified_by'     => $result['success'] ? auth()->id() : null,
                'is_current'      => true,
            ]);
        });

        return response()->json([
            'ok'        => true,
            'ocr'       => $result,
            'image_url' => $this->publicUrl($path),
            'policy'    => [
                'id'              => $policy->id,
                'carrier'         => $policy->carrier,
                'policy_number'   => $policy->policy_number,
                'named_insured'   => $policy->named_insured,
                'expiration_date' => optional($policy->expiration_date)->toDateString(),
                'is_expired'      => $policy->isExpired(),
            ],
        ]);
    }

    // ── Additional drivers ─────────────────────────────────────────

    public function addAdditionalDriver(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:120',
            'dl_number'     => 'nullable|string|max:40',
            'dl_state'      => 'nullable|string|max:2',
            'dl_expiration' => 'nullable|date',
            'dl_image'      => 'nullable|file|max:51200|mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp',
            'notes'         => 'nullable|string',
        ]);

        $imagePath = $request->hasFile('dl_image')
            ? $request->file('dl_image')->store("additional-drivers/{$reservation->id}", 'public')
            : null;

        $driver = $reservation->additionalDrivers()->create([
            'name'           => $data['name'],
            'phone'          => $data['phone']         ?? null,
            'email'          => $data['email']         ?? null,
            'dl_number'      => $data['dl_number']     ?? null,
            'dl_state'       => $data['dl_state']      ?? null,
            'dl_expiration'  => $data['dl_expiration'] ?? null,
            'dl_image_path'  => $imagePath,
            'notes'          => $data['notes']         ?? null,
        ]);

        return response()->json(['ok' => true, 'driver' => [
            'id'            => $driver->id,
            'name'          => $driver->name,
            'phone'         => $driver->phone,
            'dl_number'     => $driver->dl_number,
            'dl_state'      => $driver->dl_state,
            'dl_expiration' => optional($driver->dl_expiration)->toDateString(),
            'dl_image_url'  => $this->publicUrl($imagePath),
        ]]);
    }

    public function removeAdditionalDriver(Reservation $reservation, ReservationAdditionalDriver $driver): JsonResponse
    {
        if ($driver->reservation_id !== $reservation->id) {
            return response()->json(['message' => 'Driver does not belong to this reservation.'], 404);
        }
        $driver->delete();
        return response()->json(['ok' => true]);
    }

    // ── Inspection (existing) ──────────────────────────────────────

    public function uploadInspection(Request $request, Reservation $reservation, InspectionService $inspections): JsonResponse
    {
        $data = $request->validate([
            'type'  => 'required|in:pickup,return',
            'area'  => 'required|in:'.implode(',', ReservationInspection::REQUIRED_AREAS),
            'image' => 'required|file|max:51200|mimetypes:image/jpeg,image/png,image/heic,image/heif,image/webp,image/gif,image/bmp',
            'notes' => 'nullable|string',
        ]);

        $path = $request->file('image')->store("inspections/{$reservation->id}/{$data['type']}", 'public');
        $inspections->addInspectionImage($reservation, $data['type'], $data['area'], $path, $data['notes'] ?? null);

        return response()->json(['ok' => true, 'message' => "{$data['area']} {$data['type']} photo saved."]);
    }

    // ── Signature ──────────────────────────────────────────────────

    /**
     * Capture the renter's signature on the rental agreement. The phone
     * POSTs the data URL of a stylus-drawn PNG; we hash + store it on a
     * polymorphic Signature record bound to the reservation.
     */
    public function sign(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'signer_name'        => 'required|string|max:120',
            'signature_data_url' => 'required|string|starts_with:data:image/',
            'geo_lat'            => 'nullable|numeric',
            'geo_lng'            => 'nullable|numeric',
            'device_info'        => 'nullable|string|max:200',
        ]);

        $sig = Signature::create([
            'signable_type'      => Reservation::class,
            'signable_id'        => $reservation->id,
            'customer_id'        => $reservation->customer_id,
            'signer_name'        => $data['signer_name'],
            'signature_data_url' => $data['signature_data_url'],
            'ip_address'         => $request->ip(),
            'user_agent'         => $request->userAgent(),
            'device_info'        => $data['device_info'] ?? 'AutoGo Worker Android',
            'geo_lat'            => $data['geo_lat'] ?? null,
            'geo_lng'            => $data['geo_lng'] ?? null,
            'sha256'             => hash('sha256', $data['signature_data_url']),
            'signed_at'          => now(),
        ]);

        return response()->json(['ok' => true, 'signature_id' => $sig->id]);
    }

    // ── Pickup completion ──────────────────────────────────────────

    /**
     * Finalize pickup: persist insurance-at-pickup acknowledgement,
     * authorize the security-deposit hold (Cardknox cc:authonly via
     * SolaPaymentsService), and flip the reservation to status `rental`
     * via RentalService. Snapshot the agreement PDF so the customer
     * signature is bound to a versioned document.
     */
    public function pickup(Request $request, Reservation $reservation, RentalService $rental, SolaPaymentsService $sola): JsonResponse
    {
        $data = $request->validate([
            'odometer_out'           => 'required|integer|min:0',
            'fuel_out'               => 'required|string|max:30',
            'pickup_notes'           => 'nullable|string',
            'insurance_source'       => 'required|in:own_policy,credit_card,none',
            'insurance_company_seen' => 'nullable|string|max:120',
            'insurance_policy_seen'  => 'nullable|string|max:60',
            'hold_amount'            => 'required|numeric|min:0|max:10000',
            'hold_card_brand'        => 'nullable|string|max:20',
            'hold_card_number'       => 'nullable|string|max:20',
            'hold_card_last4'        => 'nullable|string|size:4',
            'hold_card_exp'          => 'nullable|string|max:7',
            'hold_card_cvc'          => 'nullable|string|max:4',
        ]);

        if ($reservation->status !== 'open') {
            throw ValidationException::withMessages(['status' => ['Pickup can only be completed on an open reservation.']]);
        }

        $missing = ReservationInspection::getMissingAreas($reservation->id, 'pickup');
        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'inspection' => ['Missing pickup photos: '.implode(', ', $missing)],
            ]);
        }

        $signed = Signature::where('signable_type', Reservation::class)
            ->where('signable_id', $reservation->id)->exists();
        if (!$signed) {
            throw ValidationException::withMessages(['signature' => ['Customer signature is required.']]);
        }

        $reservation->update([
            'insurance_source'       => $data['insurance_source'],
            'insurance_company_seen' => $data['insurance_company_seen'] ?? null,
            'insurance_policy_seen'  => $data['insurance_policy_seen']  ?? null,
        ]);

        if ($data['hold_amount'] > 0 && (!empty($data['hold_card_number']) || !empty($data['hold_card_last4']))) {
            // Derive last4 from the typed PAN if the worker only entered one of them.
            $last4 = $data['hold_card_last4']
                ?? (!empty($data['hold_card_number']) ? substr(preg_replace('/\D/', '', $data['hold_card_number']), -4) : null);

            $auth = $sola->authorizeHold(
                card: [
                    'number' => $data['hold_card_number'] ?? null,
                    'cvc'    => $data['hold_card_cvc']    ?? null,
                    'last4'  => $last4,
                    'brand'  => $data['hold_card_brand']  ?? null,
                    'exp'    => $data['hold_card_exp']    ?? null,
                ],
                amount: (float) $data['hold_amount'],
                description: "Security deposit — rental #{$reservation->reservation_number}",
            );

            ReservationHold::create([
                'reservation_id'        => $reservation->id,
                'amount'                => $data['hold_amount'],
                'card_brand'            => $data['hold_card_brand'] ?? null,
                'card_last4'            => $last4,
                'card_exp'              => $data['hold_card_exp']   ?? null,
                'sola_authorization_id' => $auth['authorization_id'] ?? null,
                'status'                => ($auth['ok'] ?? false) ? 'authorized' : 'failed',
                'placed_at'             => now(),
                'notes'                 => ($auth['mock'] ?? false) ? 'MOCK — Sola not yet configured' : null,
            ]);
        }

        $rental->pickupVehicle($reservation, Arr::only($data, ['odometer_out', 'fuel_out', 'pickup_notes']));

        \App\Jobs\GenerateAgreementSnapshot::dispatch($reservation->id, 'pickup_completed', 'rental_agreement');

        return response()->json([
            'ok'      => true,
            'message' => 'Pickup completed. Reservation is now active.',
        ]);
    }

    // ── Return ─────────────────────────────────────────────────────

    public function returnVehicle(Request $request, Reservation $reservation, RentalService $rental): JsonResponse
    {
        $data = $request->validate([
            'odometer_in'  => 'required|integer|min:0',
            'fuel_in'      => 'required|string|max:30',
            'return_notes' => 'nullable|string',
        ]);

        if ($reservation->status !== 'rental') {
            throw ValidationException::withMessages(['status' => ['Return can only be completed on an active rental.']]);
        }

        $missing = ReservationInspection::getMissingAreas($reservation->id, 'return');
        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'inspection' => ['Missing return photos: '.implode(', ', $missing)],
            ]);
        }

        $rental->returnVehicle($reservation, $data);

        \App\Jobs\GenerateAgreementSnapshot::dispatch($reservation->id, 'return_completed', 'return_receipt');

        return response()->json([
            'ok'      => true,
            'message' => 'Return completed.',
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function reservationListItem(Reservation $r): array
    {
        return [
            'id'                 => $r->id,
            'reservation_number' => $r->reservation_number,
            'status'             => $r->status,
            'pickup_date'        => optional($r->pickup_date)->toIso8601String(),
            'return_date'        => optional($r->return_date)->toIso8601String(),
            'customer_name'      => trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')) ?: null,
            'customer_phone'     => $r->customer?->phone,
            'vehicle_label'      => $r->vehicle ? trim("{$r->vehicle->year} {$r->vehicle->make} {$r->vehicle->model} ({$r->vehicle->license_plate})") : null,
            'pickup_location'    => $r->pickup_location,
            'return_location'    => $r->return_location,
        ];
    }

    /**
     * Inspection state shaped exactly the way the Android app expects:
     *   { "pickup": { "front": "https://...jpg", ... }, "return": { ... } }
     *
     * Cast each inner bucket to (object) so an empty bucket JSON-encodes as
     * `{}` not `[]`. Without this, `json_encode` flattens an empty PHP array
     * to a JSON array, and the Android client (kotlinx.serialization) chokes
     * trying to deserialize `[]` into a `Map<String, String>`.
     */
    private function buildInspectionState(Reservation $reservation): array
    {
        $pickup = [];
        $return = [];
        foreach ($reservation->inspections as $insp) {
            if ($insp->type === 'pickup') $pickup[$insp->area] = $this->publicUrl($insp->image_path);
            elseif ($insp->type === 'return') $return[$insp->area] = $this->publicUrl($insp->image_path);
        }
        return [
            'pickup' => (object) $pickup,
            'return' => (object) $return,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
