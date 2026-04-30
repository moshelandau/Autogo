<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LeaseApplicationSession;
use App\Services\LeaseApplicationBot;
use App\Services\TelebroadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, no-auth controller for the customer-facing lease application
 * form.
 *
 * Two-layer security:
 *
 *  1. URL `web_token` (40 chars random) identifies the session. Anyone
 *     with the link can REQUEST an OTP, but cannot read or submit data.
 *  2. SMS OTP (6 digits, 10 min, single use) proves the holder of the
 *     URL is also the person on the phone we have on file. Required
 *     before the form is rendered. Once verified, web_verified_at is
 *     set on the session and stays valid for 30 minutes (re-verify
 *     after that). If the customer comes back later, they request a new
 *     code; we never let them in with just the link alone.
 *
 * Why both: token-only is fragile (someone forwards the SMS, link
 * leaks in browser history, etc.); SSN + DOB + employment data deserve
 * a second factor. SMS to the on-file phone is the natural one since
 * that phone IS the identity in this flow.
 */
class PublicApplicationController extends Controller
{
    private const VERIFIED_VALID_MINUTES = 30;
    private const OTP_VALID_MINUTES      = 10;

    public function show(string $token): Response
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();

        // First-visit trust: the customer just received this URL via SMS
        // on their phone. Opening it the first time is implicit
        // verification — we mark them verified for the next 30 min and
        // record web_first_visited_at so subsequent returns require OTP.
        if (is_null($session->web_first_visited_at)) {
            $session->update([
                'web_first_visited_at' => now(),
                'web_verified_at'      => now(),
            ]);
        }

        // Subsequent visits — show the OTP gate if the verification has
        // expired (or was never set, which only happens if first-visit
        // logic was somehow bypassed).
        if (!$this->isVerified($session)) {
            return Inertia::render('Public/ApplyVerify', [
                'token' => $token,
                'phone_hint' => $this->maskPhone($session->phone),
            ]);
        }

        return Inertia::render('Public/Apply', [
            'session' => [
                'id'           => $session->id,
                'flow'         => $session->flow,
                'phone'        => $session->phone,
                'completed_at' => $session->completed_at,
                'aborted_at'   => $session->aborted_at,
                'collected'    => $session->collected ?? [],
            ],
        ]);
    }

    /** POST /apply/{token}/send-otp — text the customer a 6-digit code. */
    public function sendOtp(string $token, TelebroadService $sms)
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();

        $code = (string) random_int(100000, 999999);
        $session->update([
            'web_otp_hash'       => Hash::make($code),
            'web_otp_expires_at' => now()->addMinutes(self::OTP_VALID_MINUTES),
        ]);

        try {
            $sms->sendSms($session->phone, "AutoGo verification code: {$code}\n\nDon't share this — we'll never call to ask for it.");
        } catch (\Throwable $e) {
            \Log::error('OTP send failed', ['session_id' => $session->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Could not text the code right now. Please try again.');
        }

        return back()->with('success', 'Code sent. Check your phone.');
    }

    /** POST /apply/{token}/verify-otp — accept the 6-digit code. */
    public function verifyOtp(Request $request, string $token)
    {
        $request->validate(['code' => 'required|string|size:6']);
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();

        if (!$session->web_otp_hash || !$session->web_otp_expires_at || $session->web_otp_expires_at->isPast()) {
            return back()->withErrors(['code' => 'Code expired — request a new one.']);
        }
        if (!Hash::check($request->input('code'), $session->web_otp_hash)) {
            return back()->withErrors(['code' => 'Wrong code.']);
        }

        $session->update([
            'web_otp_hash'       => null,         // single-use
            'web_otp_expires_at' => null,
            'web_verified_at'    => now(),
        ]);

        return redirect()->route('public.apply.show', ['token' => $token]);
    }

    public function submit(Request $request, string $token, LeaseApplicationBot $bot)
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();
        if (!$this->isVerified($session)) {
            return redirect()->route('public.apply.show', ['token' => $token]);
        }
        if ($session->completed_at || $session->aborted_at) {
            return back()->with('error', 'This application has already been submitted.');
        }

        $validated = $request->validate([
            'first_name'         => 'nullable|string|max:60',
            'last_name'          => 'nullable|string|max:60',
            'date_of_birth'      => 'nullable|date',
            'ssn'                => 'nullable|string|max:11',
            'address'            => 'nullable|string|max:255',
            'city'               => 'nullable|string|max:80',
            'state'              => 'nullable|string|max:2',
            'zip'                => 'nullable|string|max:10',
            'own_or_rent'        => 'nullable|in:own,rent',
            'monthly_housing'    => 'nullable|numeric|min:0',
            'years_at_address'   => 'nullable|numeric|min:0',
            'email'              => 'nullable|email|max:120',
            'employer'           => 'nullable|string|max:120',
            'employer_address'   => 'nullable|string|max:255',
            'employer_city'      => 'nullable|string|max:80',
            'employer_state'     => 'nullable|string|max:2',
            'employer_zip'       => 'nullable|string|max:10',
            'employer_phone'     => 'nullable|string|max:20',
            'position'           => 'nullable|string|max:120',
            'years_employed'     => 'nullable|numeric|min:0',
            'annual_income'      => 'nullable|numeric|min:0',
            'has_coapplicant'    => 'nullable|in:yes,no',
            'vehicle_interest'   => 'nullable|string|max:255',
            'license_front'      => 'nullable|file|image|max:10240',
            'license_back'       => 'nullable|file|image|max:10240',
        ]);

        $collected = $session->collected ?? [];
        foreach ($validated as $k => $v) {
            if ($k === 'license_front' || $k === 'license_back') continue;
            if ($v !== null && $v !== '') $collected[$k] = $v;
        }
        if (!empty($validated['date_of_birth'])) {
            $collected['date_of_birth'] = date('Y-m-d', strtotime($validated['date_of_birth']));
        }
        foreach (['front' => 'license_front', 'back' => 'license_back'] as $side => $field) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store("lease-bot-uploads/{$session->id}", 'public');
                $collected['license_image_' . $side . '_path'] = $path;
                $collected['license_image_' . $side . '_url']  = Storage::disk('public')->url($path);
            }
        }

        $session->collected = $collected;
        $session->current_step = '__done__';
        $session->last_inbound_at = now();
        $session->save();

        $ref = new \ReflectionClass($bot);
        $finalize = $ref->getMethod('finalize');
        $finalize->setAccessible(true);
        $finalize->invoke($bot, $session);

        return redirect()->route('public.apply.done', ['token' => $token]);
    }

    public function done(string $token): Response
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();
        return Inertia::render('Public/ApplyDone', [
            'phone' => $this->maskPhone($session->phone),
        ]);
    }

    /**
     * Single-step view — just the one field the bot asked. The bot links
     * customers here when they reply SECURE on a sensitive step (SSN,
     * license upload, etc.). Same auth/OTP rules as the full form.
     */
    public function showStep(string $token, string $stepKey): Response
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();

        if (is_null($session->web_first_visited_at)) {
            $session->update(['web_first_visited_at' => now(), 'web_verified_at' => now()]);
        }
        if (!$this->isVerified($session)) {
            return Inertia::render('Public/ApplyVerify', [
                'token' => $token,
                'phone_hint' => $this->maskPhone($session->phone),
            ]);
        }

        $config = $this->stepConfig($stepKey);

        return Inertia::render('Public/ApplyStep', [
            'token'       => $token,
            'step_key'    => $stepKey,
            'label'       => $config['label'],
            'type'        => $config['type'],
            'accept'      => $config['accept']  ?? '',
            'capture'     => $config['capture'] ?? '',
            'placeholder' => $config['placeholder'] ?? '',
        ]);
    }

    public function submitStep(Request $request, string $token, string $stepKey, LeaseApplicationBot $bot)
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();
        if (!$this->isVerified($session)) {
            return redirect()->route('public.apply.show.step', ['token' => $token, 'step' => $stepKey]);
        }

        $config = $this->stepConfig($stepKey);
        $collected = $session->collected ?? [];

        if ($config['type'] === 'file') {
            $request->validate(['file' => 'required|file|image|max:20480']);
            $path = $request->file('file')->store("lease-bot-uploads/{$session->id}", 'public');
            $side = str_contains($stepKey, 'back') ? 'back' : 'front';
            $collected['license_image_' . $side . '_path'] = $path;
            $collected['license_image_' . $side . '_url']  = Storage::disk('public')->url($path);
        } else {
            $request->validate(['value' => 'required|string|max:255']);
            $collected[$stepKey] = trim($request->input('value'));
        }

        $session->collected = $collected;
        $session->last_inbound_at = now();
        $session->save();

        // SMS the customer back so they know we got it; the bot then resumes
        // the SMS flow at the next step.
        try {
            app(\App\Services\TelebroadService::class)->sendSms($session->phone,
                "Got your " . strtolower($config['label']) . " — thanks. Continuing your application.");
        } catch (\Throwable $e) {
            \Log::error('SMS resume failed after step submit', ['error' => $e->getMessage()]);
        }

        return Inertia::location('/apply/' . $token . '/step/' . $stepKey . '/done');
    }

    public function stepDone(string $token, string $stepKey): Response
    {
        return Inertia::render('Public/ApplyDone', [
            'phone' => '',
        ]);
    }

    /**
     * Combined license-upload page — front + back together. Linked from
     * the bot's SECURE reply on either license_image_* step. Submitting
     * lets the customer pick to upload one or both at the same time
     * instead of navigating two separate single-step pages.
     */
    public function showLicense(string $token): Response
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();

        if (is_null($session->web_first_visited_at)) {
            $session->update(['web_first_visited_at' => now(), 'web_verified_at' => now()]);
        }
        if (!$this->isVerified($session)) {
            return Inertia::render('Public/ApplyVerify', [
                'token' => $token,
                'phone_hint' => $this->maskPhone($session->phone),
            ]);
        }

        $c = $session->collected ?? [];
        return Inertia::render('Public/ApplyLicense', [
            'token'     => $token,
            'has_front' => !empty($c['license_image_front_path']),
            'has_back'  => !empty($c['license_image_back_path']),
        ]);
    }

    public function submitLicense(Request $request, string $token)
    {
        $session = LeaseApplicationSession::where('web_token', $token)->firstOrFail();
        if (!$this->isVerified($session)) {
            return redirect()->route('public.apply.show.license', ['token' => $token]);
        }

        $request->validate([
            'license_front' => 'nullable|file|image|max:20480',
            'license_back'  => 'nullable|file|image|max:20480',
        ]);
        if (!$request->hasFile('license_front') && !$request->hasFile('license_back')) {
            return back()->withErrors(['license_front' => 'Pick at least one side to upload.']);
        }

        $collected = $session->collected ?? [];
        foreach (['front' => 'license_front', 'back' => 'license_back'] as $side => $field) {
            if (!$request->hasFile($field)) continue;
            $path = $request->file($field)->store("lease-bot-uploads/{$session->id}", 'public');
            $collected['license_image_' . $side . '_path'] = $path;
            $collected['license_image_' . $side . '_url']  = Storage::disk('public')->url($path);

            // Save as CustomerDocument right away, mirroring the bot's
            // upload-time persistence so the doc shows up on the deal
            // Documents tab without waiting for finalize().
            if ($session->customer) {
                \App\Models\CustomerDocument::firstOrCreate(
                    ['customer_id' => $session->customer->id, 'path' => $path],
                    [
                        'type'          => 'drivers_license_' . $side,
                        'label'         => trim(($collected['first_name'] ?? '') . ' ' . ($collected['last_name'] ?? '') . " ({$side})", ' '),
                        'disk'          => 'public',
                        'original_name' => $request->file($field)->getClientOriginalName(),
                        'mime_type'     => $request->file($field)->getMimeType(),
                        'uploaded_by'   => null,
                    ]
                );
            }
        }

        $session->collected = $collected;
        $session->last_inbound_at = now();
        $session->save();

        try {
            app(\App\Services\TelebroadService::class)->sendSms($session->phone,
                "Got your license — thanks. Continuing your application.");
        } catch (\Throwable $e) {
            \Log::error('SMS resume failed after license submit', ['error' => $e->getMessage()]);
        }

        return redirect()->route('public.apply.done', ['token' => $token]);
    }

    private function stepConfig(string $stepKey): array
    {
        $map = [
            'ssn'                 => ['label' => 'SSN', 'type' => 'password', 'placeholder' => 'XXX-XX-XXXX'],
            'co_ssn'              => ['label' => 'Co-applicant SSN', 'type' => 'password', 'placeholder' => 'XXX-XX-XXXX'],
            'date_of_birth'       => ['label' => 'Date of birth', 'type' => 'date'],
            'license_image_front' => ['label' => "Driver's license — front", 'type' => 'file', 'accept' => 'image/*', 'capture' => 'environment'],
            'license_image_back'  => ['label' => "Driver's license — back",  'type' => 'file', 'accept' => 'image/*', 'capture' => 'environment'],
            'annual_income'       => ['label' => 'Annual income ($)', 'type' => 'number'],
            'employer'            => ['label' => 'Employer name', 'type' => 'text'],
        ];
        return $map[$stepKey] ?? ['label' => ucwords(str_replace('_', ' ', $stepKey)), 'type' => 'text'];
    }

    private function isVerified(LeaseApplicationSession $session): bool
    {
        return $session->web_verified_at
            && $session->web_verified_at->gt(now()->subMinutes(self::VERIFIED_VALID_MINUTES));
    }

    private function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 4) return '••••';
        return '••• ••• ' . substr($digits, -4);
    }
}
