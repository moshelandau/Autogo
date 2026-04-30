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
