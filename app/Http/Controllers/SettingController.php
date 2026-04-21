<?php

namespace App\Http\Controllers;

use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => $this->settings->getAll(),
            'env' => [
                // Surface what's configured in .env so the UI can show "configured ✓"
                'telebroad'    => !empty(config('services.telebroad.username')),
                'twilio'       => !empty(config('services.twilio.sid')),
                'sola'         => !empty(config('services.sola.api_key')),
                'credit700'    => !empty(config('services.credit700.api_key')),
                'asana'        => !empty(config('services.asana.token')),
                'hq_rentals'   => !empty(config('services.hq_rentals.api_key')),
                'ccc_one'      => !empty(config('services.ccc_one.username')),
                'towbook'      => !empty(config('services.towbook.api_key')) || !empty(config('services.towbook.username')),
                'swoop'        => !empty(config('services.swoop.api_key')),
                'allstate_roadside' => !empty(config('services.allstate_roadside.api_key')) || !empty(config('services.allstate_roadside.username')),
                'mail'         => !empty(config('mail.mailers.smtp.host')),
                's3'           => !empty(config('filesystems.disks.s3.key')),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
            'settings.*.group' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            $this->settings->set($setting['key'], $setting['value'], $setting['group'] ?? 'general');
        }

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Test an integration. Returns JSON {ok, message, detail?}.
     */
    public function test(Request $request, string $integration)
    {
        try {
            return response()->json(match ($integration) {
                'telebroad' => $this->testTelebroad($request),
                'twilio'    => $this->testTwilio($request),
                'sola'              => $this->testSola($request),
                'sola_autogo'       => $this->testSolaAccount($request, 'autogo'),
                'sola_high_rental'  => $this->testSolaAccount($request, 'high_rental'),
                'credit700' => $this->testCredit700($request),
                'asana'     => $this->testAsana($request),
                'hq_rentals'=> $this->testHqRentals($request),
                'ccc_one'   => $this->testCccOne($request),
                'towbook'   => $this->testTowbook($request),
                'swoop'     => $this->testSwoop($request),
                'allstate_roadside' => $this->testAllstate($request),
                'mail'      => $this->testMail($request),
                's3'        => $this->testS3($request),
                default     => ['ok' => false, 'message' => "Unknown integration: {$integration}"],
            });
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ]);
        }
    }

    private function testTelebroad(Request $r): array
    {
        $u   = $r->input('username') ?: config('services.telebroad.username');
        $p   = $r->input('password') ?: config('services.telebroad.password');
        $url = $r->input('api_url')  ?: config('services.telebroad.api_url');
        if (!$u || !$p) return ['ok' => false, 'message' => 'Telebroad username + password required'];

        // Telebroad TeleConsole REST — basic auth ping
        $resp = Http::withBasicAuth($u, $p)->acceptJson()->get($url . '/extensions');

        return $resp->successful()
            ? ['ok' => true,  'message' => 'Telebroad reachable. Extensions: ' . count($resp->json() ?? [])]
            : ['ok' => false, 'message' => 'Telebroad rejected (HTTP ' . $resp->status() . ')'];
    }

    private function testTwilio(Request $r): array
    {
        $sid   = $r->input('sid')   ?: config('services.twilio.sid');
        $token = $r->input('token') ?: config('services.twilio.token');
        if (!$sid || !$token) return ['ok' => false, 'message' => 'Twilio SID + Token required'];

        $resp = Http::withBasicAuth($sid, $token)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}.json");

        return $resp->successful()
            ? ['ok' => true,  'message' => 'Twilio reachable. Account: ' . ($resp->json('friendly_name') ?? 'unnamed') . ' · Status: ' . ($resp->json('status') ?? '?')]
            : ['ok' => false, 'message' => 'Twilio rejected credentials (HTTP ' . $resp->status() . ')'];
    }

    private function testSola(Request $r): array
    {
        // Generic Sola test (kept for backward compat) — defaults to AutoGo account.
        return $this->testSolaAccount($r, 'autogo');
    }

    /**
     * Test a specific Sola/Cardknox merchant xKey by calling the Cardknox Gateway.
     */
    private function testSolaAccount(Request $r, string $account): array
    {
        $xKey = $r->input('api_key'); // From the field the user just typed (not yet saved)
        if (!$xKey) return ['ok' => false, 'message' => 'xKey for ' . ($account === 'high_rental' ? 'High Car Rental' : 'AutoGo') . ' is empty.'];

        // Temporarily override the config so the service uses THIS key for the test
        $originalKey = $account === 'high_rental' ? config('services.sola.webhook_secret') : config('services.sola.api_key');
        if ($account === 'high_rental') config(['services.sola.webhook_secret' => $xKey]);
        else                            config(['services.sola.api_key'        => $xKey]);

        try {
            /** @var \App\Services\SolaPaymentsService $svc */
            $svc = app(\App\Services\SolaPaymentsService::class);
            return $svc->test($account);
        } finally {
            // Restore
            if ($account === 'high_rental') config(['services.sola.webhook_secret' => $originalKey]);
            else                            config(['services.sola.api_key'        => $originalKey]);
        }
    }

    private function testCredit700(Request $r): array
    {
        $key = $r->input('api_key') ?: config('services.credit700.api_key');
        $url = $r->input('api_url') ?: config('services.credit700.api_url', 'https://api.700credit.com');
        if (!$key) return ['ok' => false, 'message' => '700Credit API key required'];

        $resp = Http::withToken($key)->acceptJson()->get("{$url}/health");

        return $resp->successful()
            ? ['ok' => true,  'message' => "700Credit reachable at {$url}"]
            : ['ok' => false, 'message' => '700Credit rejected (HTTP ' . $resp->status() . '). Verify key + endpoint with vendor.'];
    }

    private function testAsana(Request $r): array
    {
        $token = $r->input('token') ?: config('services.asana.token');
        if (!$token) return ['ok' => false, 'message' => 'Asana PAT required'];

        $resp = Http::withToken($token)->acceptJson()->get('https://app.asana.com/api/1.0/users/me');

        return $resp->successful()
            ? ['ok' => true,  'message' => 'Asana OK · User: ' . ($resp->json('data.name') ?? '?')]
            : ['ok' => false, 'message' => 'Asana rejected token (HTTP ' . $resp->status() . ')'];
    }

    private function testHqRentals(Request $r): array
    {
        $key = $r->input('api_key') ?: config('services.hq_rentals.api_key');
        $sub = $r->input('subdomain') ?: config('services.hq_rentals.subdomain', 'highrental');
        if (!$key) return ['ok' => false, 'message' => 'HQ Rentals API key required'];

        $resp = Http::withToken($key)->acceptJson()
            ->get("https://{$sub}.us5.hqrentals.app/api/integration/v1/locations");

        return $resp->successful()
            ? ['ok' => true,  'message' => 'HQ Rentals reachable. Locations: ' . count($resp->json('data') ?? [])]
            : ['ok' => false, 'message' => 'HQ Rentals rejected (HTTP ' . $resp->status() . ')'];
    }

    private function testCccOne(Request $r): array
    {
        $u = $r->input('username') ?: config('services.ccc_one.username');
        $p = $r->input('password') ?: config('services.ccc_one.password');
        if (!$u || !$p) return ['ok' => false, 'message' => 'CCC ONE username + password required'];

        // CCC ONE has no public REST API — we'd integrate via their EMS/Estimate XML feed or their portal scrape.
        return ['ok' => false, 'message' => 'CCC ONE has no public API; integration is a planned scraper. Credentials stored.'];
    }

    private function testTowbook(Request $r): array
    {
        $cid = $r->input('client_id')     ?: config('services.towbook.client_id');
        $sec = $r->input('client_secret') ?: config('services.towbook.client_secret');
        if (!$cid || !$sec) return ['ok' => false, 'message' => 'TowBook client_id + client_secret required (request from support@towbook.com).'];

        $resp = Http::asForm()->post('https://api.towbook.com/oauth/token', [
            'grant_type'    => 'client_credentials',
            'client_id'     => $cid,
            'client_secret' => $sec,
            'scope'         => 'read write',
        ]);
        if (!$resp->successful()) {
            return ['ok' => false, 'message' => 'TowBook OAuth rejected (HTTP '.$resp->status().'): '.substr($resp->body(), 0, 150)];
        }
        $token = $resp->json('access_token');
        if (!$token) return ['ok' => false, 'message' => 'No access_token returned: '.substr($resp->body(), 0, 200)];

        $me = Http::withToken($token)->acceptJson()->get('https://api.towbook.com/v1/me');
        return $me->successful()
            ? ['ok' => true,  'message' => 'TowBook OAuth + API OK. Account: '.($me->json('company.name') ?? $me->json('name') ?? 'connected')]
            : ['ok' => true,  'message' => 'TowBook OAuth OK (token granted). /me endpoint returned HTTP '.$me->status()];
    }

    private function testSwoop(Request $r): array
    {
        $key = $r->input('api_key') ?: config('services.swoop.api_key');
        $env = $r->input('env')     ?: config('services.swoop.env', 'sandbox');
        if (!$key) return ['ok' => false, 'message' => 'Swoop API key required (apply at https://www.swoopapi.com/)'];
        $base = $env === 'live' ? 'https://api.joinswoop.com' : 'https://api-staging.joinswoop.com';
        $resp = Http::withToken($key)->acceptJson()->get("{$base}/api/v1/jobs?limit=1");
        return $resp->successful()
            ? ['ok' => true,  'message' => "Swoop {$env} reachable."]
            : ['ok' => false, 'message' => 'Swoop rejected (HTTP '.$resp->status().')'];
    }

    private function testAllstate(Request $r): array
    {
        $key = $r->input('api_key') ?: config('services.allstate_roadside.api_key');
        if (!$key) return ['ok' => false, 'message' => 'Allstate Roadside API key required (apply via Allstate Partner Services)'];
        return ['ok' => false, 'message' => 'Allstate Roadside API integration is partner-restricted. Credentials saved; live test pending API documentation from Allstate.'];
    }

    private function testMail(Request $r): array
    {
        $to = $r->input('to') ?: auth()->user()?->email;
        if (!$to) return ['ok' => false, 'message' => 'Recipient required'];

        Mail::raw("AutoGo SMTP test — sent " . now()->toDateTimeString(), function ($m) use ($to) {
            $m->to($to)->subject('AutoGo SMTP Test');
        });

        return ['ok' => true, 'message' => "Sent test email to {$to} via " . config('mail.default')];
    }

    private function testS3(Request $r): array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('s3');
        $key = 'autogo-test-' . now()->timestamp . '.txt';
        $disk->put($key, 'hello from autogo at ' . now()->toIso8601String());
        $exists = $disk->exists($key);
        $disk->delete($key);

        return $exists
            ? ['ok' => true,  'message' => 'S3 write+read+delete OK (bucket: ' . config('filesystems.disks.s3.bucket') . ')']
            : ['ok' => false, 'message' => 'S3 wrote but readback failed'];
    }
}
