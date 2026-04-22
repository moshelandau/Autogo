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
        // Strip secret values out of the public settings map and send a parallel
        // `secrets` map with masked previews instead. The UI shows "✓ saved (ends ●●●1234)"
        // for any field that has a stored value, so users know one is saved without
        // exposing the full value in HTML.
        $all = $this->settings->getAll();
        $secrets = [];
        $publicSettings = [];
        foreach ($all as $k => $v) {
            if ($this->isSecretKey($k)) {
                $publicSettings[$k] = ''; // never echo secrets back
                if ($v !== null && $v !== '') {
                    $str = (string) $v;
                    $secrets[$k] = [
                        'has'    => true,
                        'length' => strlen($str),
                        'masked' => str_repeat('•', max(0, min(8, strlen($str) - 4))) . substr($str, -4),
                    ];
                }
            } else {
                $publicSettings[$k] = $v;
            }
        }

        return Inertia::render('Settings/Index', [
            'settings' => $publicSettings,
            'secrets'  => $secrets,
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
                'anthropic'    => !empty(config('services.anthropic.api_key')) || !empty(\App\Models\Setting::getValue('anthropic_api_key')),
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
            // Don't wipe a saved secret just because the user left the field blank.
            // Empty strings on secret-type keys are preserved as a no-op.
            if ($this->isSecretKey($setting['key']) && ($setting['value'] === null || $setting['value'] === '')) {
                continue;
            }
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

    /** Centralised "is this key sensitive?" check. */
    private function isSecretKey(string $key): bool
    {
        $patterns = ['password', 'secret', 'token', 'api_key', 'xkey', 's3_key', 's3_secret', 'access_key'];
        $lower = strtolower($key);
        foreach ($patterns as $p) if (str_contains($lower, $p)) return true;
        return false;
    }

    /** Helper: read a tested value from request, fall back to saved Setting, then to .env config. */
    private function tval(Request $r, string $field, string $settingKey, string $configPath): ?string
    {
        $v = $r->input($field);
        if ($v !== null && $v !== '') return (string) $v;
        $s = \App\Models\Setting::getValue($settingKey);
        if ($s !== null && $s !== '') return (string) $s;
        return config($configPath) ?: null;
    }

    private function testTelebroad(Request $r): array
    {
        $u   = $this->tval($r, 'username', 'telebroad_username',     'services.telebroad.username');
        $p   = $this->tval($r, 'password', 'telebroad_password',     'services.telebroad.password');
        $url = $this->tval($r, 'api_url',  'telebroad_api_url',      'services.telebroad.api_url');
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
        // Field name in the form is "api_key" for both; but the SAVED setting key
        // differs per merchant.
        $settingKey = $account === 'high_rental' ? 'sola_webhook_secret' : 'sola_api_key';
        $configPath = $account === 'high_rental' ? 'services.sola.webhook_secret' : 'services.sola.api_key';
        $xKey = $this->tval($r, 'api_key', $settingKey, $configPath);
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
        $key = $this->tval($r, 'api_key', 'credit700_api_key', 'services.credit700.api_key');
        $url = $this->tval($r, 'api_url', 'credit700_api_url', 'services.credit700.api_url') ?: 'https://api.700credit.com';
        if (!$key) return ['ok' => false, 'message' => '700Credit API key required'];

        $resp = Http::withToken($key)->acceptJson()->get("{$url}/health");

        return $resp->successful()
            ? ['ok' => true,  'message' => "700Credit reachable at {$url}"]
            : ['ok' => false, 'message' => '700Credit rejected (HTTP ' . $resp->status() . '). Verify key + endpoint with vendor.'];
    }

    private function testAsana(Request $r): array
    {
        $token = $this->tval($r, 'token', 'asana_token', 'services.asana.token');
        if (!$token) return ['ok' => false, 'message' => 'Asana PAT required'];

        $resp = Http::withToken($token)->acceptJson()->get('https://app.asana.com/api/1.0/users/me');

        return $resp->successful()
            ? ['ok' => true,  'message' => 'Asana OK · User: ' . ($resp->json('data.name') ?? '?')]
            : ['ok' => false, 'message' => 'Asana rejected token (HTTP ' . $resp->status() . ')'];
    }

    private function testHqRentals(Request $r): array
    {
        $key = $this->tval($r, 'api_key',   'hq_rentals_api_key',   'services.hq_rentals.api_key');
        $sub = $this->tval($r, 'subdomain', 'hq_rentals_subdomain', 'services.hq_rentals.subdomain') ?: 'highrental';
        if (!$key) return ['ok' => false, 'message' => 'HQ Rentals API key required'];

        $resp = Http::withToken($key)->acceptJson()
            ->get("https://{$sub}.us5.hqrentals.app/api/integration/v1/locations");

        return $resp->successful()
            ? ['ok' => true,  'message' => 'HQ Rentals reachable. Locations: ' . count($resp->json('data') ?? [])]
            : ['ok' => false, 'message' => 'HQ Rentals rejected (HTTP ' . $resp->status() . ')'];
    }

    private function testCccOne(Request $r): array
    {
        $u = $this->tval($r, 'username', 'ccc_one_username', 'services.ccc_one.username');
        $p = $this->tval($r, 'password', 'ccc_one_password', 'services.ccc_one.password');
        if (!$u || !$p) return ['ok' => false, 'message' => 'CCC ONE username + password required'];

        // CCC ONE has no public REST API — we'd integrate via their EMS/Estimate XML feed or their portal scrape.
        return ['ok' => false, 'message' => 'CCC ONE has no public API; integration is a planned scraper. Credentials stored.'];
    }

    private function testTowbook(Request $r): array
    {
        $cid = $this->tval($r, 'client_id',     'towbook_client_id',     'services.towbook.client_id');
        $sec = $this->tval($r, 'client_secret', 'towbook_client_secret', 'services.towbook.client_secret');
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
        $key = $this->tval($r, 'api_key', 'swoop_api_key', 'services.swoop.api_key');
        $env = $this->tval($r, 'env',     'swoop_env',     'services.swoop.env') ?: 'sandbox';
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
