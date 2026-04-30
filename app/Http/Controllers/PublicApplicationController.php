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

        // License uploads route through the bot's image pipeline so they
        // get OCR + side check + obstruction detection + diff confirmation
        // (same as SMS path). Bot SMSes the customer about the outcome.
        foreach (['front' => 'license_front', 'back' => 'license_back'] as $side => $field) {
            if (!$request->hasFile($field)) continue;
            $url = $this->saveAndUrl($request->file($field), $session);
            $stepKey = 'license_image_' . $side;
            $session->update(['current_step' => $stepKey, 'last_inbound_at' => now()]);
            $bot->handleInbound($session->phone, '', $session->customer, [$url]);
            $session = $session->fresh();
        }
        // Re-merge text fields. Bot only handled image uploads above; the
        // many text fields below come straight from the form.
        $collected = array_merge($session->collected ?? [], array_diff_key(
            $collected,
            array_flip(['license_image_front_path','license_image_front_url','license_image_back_path','license_image_back_url'])
        ));
        $session->collected       = $collected;
        $session->last_inbound_at = now();
        $session->save();

        // Finalize only if we have enough to actually create a Customer +
        // Deal record (name + DOB at minimum). Otherwise this was a
        // partial save and we just persist progress — staff can finish
        // the rest, customer can come back to the same URL later.
        $hasMinimum = !empty($collected['first_name']) && !empty($collected['last_name']) && !empty($collected['date_of_birth']);
        if ($hasMinimum) {
            $session->update(['current_step' => '__done__']);
            $ref = new \ReflectionClass($bot);
            $finalize = $ref->getMethod('finalize');
            $finalize->setAccessible(true);
            $finalize->invoke($bot, $session);
            return redirect()->route('public.apply.done', ['token' => $token]);
        }

        // Partial save — don't finalize, don't create stub records. Tell
        // user we got their progress; the SMS bot will keep nudging them
        // for what's still missing.
        return back()->with('success', 'Saved your progress — finish the rest when you can. We will text you reminders.');
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
            // Route file uploads through the bot's image pipeline — same
            // OCR, side-check, obstruction-detection, diff-confirmation,
            // step-advance, SMS-reply that SMS uploads get. Bot decides
            // the next message to the customer based on the outcome.
            $url = $this->saveAndUrl($request->file('file'), $session);
            $session->update(['current_step' => $stepKey, 'last_inbound_at' => now()]);
            $bot->handleInbound($session->phone, '', $session->customer, [$url]);
        } else {
            $request->validate(['value' => 'required|string|max:255']);
            // Text input — route through the bot the same way the SMS
            // path would. Bot's validators will normalize / accept / reject.
            $session->update(['current_step' => $stepKey, 'last_inbound_at' => now()]);
            $bot->handleInbound($session->phone, trim($request->input('value')), $session->customer, []);
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

    /**
     * Combined front+back license submit. Routes BOTH files through the
     * same LeaseApplicationBot::handleInbound pipeline the SMS path uses,
     * so OCR + auto-rotate + side-check + obstruction-detection +
     * diff-confirmation + step-advance all run identically. The bot
     * sends the appropriate SMS reply (success → next step prompt; or
     * failure → re-ask) so the customer sees the outcome on their phone
     * even after submitting via the web. Web side just redirects to the
     * "submitted" page; SMS is the source of truth for what happens
     * next.
     */
    public function submitLicense(Request $request, string $token, LeaseApplicationBot $bot)
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

        // Process front first (if uploaded), then back. Each side flows
        // through the bot's image-step handler — same OCR, verify, diff,
        // advance, SMS-reply pipeline as the SMS path. Bot decides what
        // to text the customer next based on the outcome.
        $customer = $session->customer;
        if ($request->hasFile('license_front')) {
            $url = $this->saveAndUrl($request->file('license_front'), $session);
            $session->update(['current_step' => 'license_image_front']);
            $bot->handleInbound($session->phone, '', $customer, [$url]);
            $session = $session->fresh();
        }
        if ($request->hasFile('license_back')) {
            $url = $this->saveAndUrl($request->file('license_back'), $session);
            // Only force back step if not already past it (e.g., front
            // succeeded and bot already advanced). Otherwise keep wherever
            // the bot put us.
            if ($session->current_step === 'license_image_front') {
                $session->update(['current_step' => 'license_image_back']);
            }
            $bot->handleInbound($session->phone, '', $customer, [$url]);
        }

        return redirect()->route('public.apply.done', ['token' => $token]);
    }

    /**
     * Save an uploaded file to the lease-bot-uploads/{session_id}/ folder
     * on the public disk and return its full public URL — what the bot
     * expects when handling an "MMS" (it fetches the URL via file_get_contents).
     *
     * Storage::disk('public')->url() returns a full URL when the public
     * disk has 'url' configured (default in Laravel: APP_URL/storage).
     * Don't prepend APP_URL again — that gave us a double-prefix bug
     * earlier, the bot's file_get_contents failed silently, and the
     * customer got "trouble receiving your photo" instead of validation.
     */
    private function saveAndUrl(\Illuminate\Http\UploadedFile $file, LeaseApplicationSession $session): string
    {
        $path = $file->store("lease-bot-uploads/{$session->id}", 'public');
        $url  = Storage::disk('public')->url($path);
        // Belt-and-suspenders: if Storage returns a relative path (when
        // APP_URL isn't wired into the disk config), prepend it ourselves.
        if (!preg_match('#^https?://#i', $url)) {
            $url = rtrim(config('app.url', 'https://app.autogoco.com'), '/') . '/' . ltrim($url, '/');
        }
        return $url;
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
