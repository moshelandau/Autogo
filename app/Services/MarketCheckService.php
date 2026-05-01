<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MarketCheck wrapper.
 *
 * ✅ VERIFIED endpoints (per https://apidocs.marketcheck.com/):
 *   - GET /v2/decode/car/{vin}/specs       — VIN → year/make/model/trim/specs
 *
 * 🟡 ASSUMED endpoints (need a sample response check before relying on):
 *   - GET /v2/search/car/active            — used-car listings
 *   - GET /v2/predict/car/price            — market value prediction
 *
 * All calls go through {@see callApi()} which enforces the free-tier
 * quota (default 500/mo) by atomically incrementing a Setting counter
 * BEFORE the HTTP call. If the call fails after counting, we don't
 * refund — the API still served the request, and refunding could let
 * a hot loop blow through the cap.
 *
 * Counter key: `marketcheck_calls_YYYY_MM` (rolls over each calendar
 * month). `marketcheck_quota_blocked_at` is set when we refuse a call
 * so the UI can show the staff a friendly explanation.
 */
class MarketCheckService
{
    public function __construct() {}

    public static function counterKey(): string
    {
        return 'marketcheck_calls_' . now()->format('Y_m');
    }

    public static function callsThisMonth(): int
    {
        return (int) Setting::getValue(self::counterKey(), 0);
    }

    public static function quota(): int
    {
        return (int) (Setting::getValue('marketcheck_monthly_quota')
            ?: config('services.marketcheck.monthly_quota', 500));
    }

    public static function callsRemaining(): int
    {
        return max(0, self::quota() - self::callsThisMonth());
    }

    public static function resetCounter(): void
    {
        Setting::setValue(self::counterKey(), 0, 'marketcheck');
        Setting::setValue('marketcheck_quota_blocked_at', null, 'marketcheck');
    }

    /**
     * ✅ VERIFIED — VIN decode.
     */
    public function decodeVin(string $vin): array
    {
        $vin = strtoupper(trim($vin));
        if (strlen($vin) !== 17) {
            return ['error' => "VIN must be 17 characters (got " . strlen($vin) . ")"];
        }
        return $this->callApi('GET', "/decode/car/{$vin}/specs");
    }

    /**
     * ✅ VERIFIED — OEM incentives by make + ZIP (non-deprecated).
     * Docs: https://docs.marketcheck.com/docs/api/cars/incentives/incentive-by-make-zip
     * Endpoint: GET /v2/search/car/incentive/{make}/{zip}
     *
     * Returns: {num_found, listings[], facets?, stats?, range_facets?}
     * Each listing has offer terms (APR, monthly_payment, down_payment,
     * cashback amounts, term, offer_type=lease|finance|cash) plus the
     * vehicle (year/make/model/trim) it applies to.
     *
     * Optional filters (whitelisted — extras are dropped):
     *   - model           ("CR-V")
     *   - year            (2026)
     *   - trim            ("EX-L")
     *   - offer_type      ("lease", "finance", "cash")
     *   - rows            (default 10, max 50)
     *   - drivetrain, transmission, engine, fuel_type
     */
    public function searchIncentivesByMakeZip(string $make, string $zip, array $filters = []): array
    {
        $make = strtolower(trim($make));
        $zip  = trim($zip);
        if (!$make || !preg_match('/^\d{5}$/', $zip)) {
            return ['error' => 'Both `make` and a 5-digit `zip` are required.'];
        }
        $allowed = ['model', 'year', 'trim', 'offer_type', 'rows',
                    'drivetrain', 'transmission', 'engine', 'fuel_type',
                    'apr_range', 'monthly_range', 'term_range',
                    'cashback_amount_range', 'cashback_target_group',
                    'facets', 'range_facets', 'stats',
                    'sort_by', 'sort_order', 'start'];
        $query = array_intersect_key($filters, array_flip($allowed));
        return $this->callApi('GET', "/search/car/incentive/{$make}/{$zip}", $query);
    }

    /**
     * 🟡 VERIFIED-DEPRECATED — generic OEM incentive search.
     * MarketCheck's docs list /v2/search/car/incentive/oem as
     * "Deprecated". Prefer searchIncentivesByMakeZip() for new code.
     */
    public function searchOemIncentives(array $filters = []): array
    {
        $allowed = ['make', 'model', 'year', 'state', 'zip', 'offer_type',
                    'trim', 'rows', 'apr_range', 'monthly_range', 'term_range'];
        $query   = array_intersect_key($filters, array_flip($allowed));
        return $this->callApi('GET', '/search/car/incentive/oem', $query);
    }

    private function callApi(string $method, string $path, array $query = []): array
    {
        $key = config('services.marketcheck.api_key') ?: Setting::getValue('marketcheck_api_key');
        if (!$key) {
            return ['error' => 'MarketCheck API key not configured. Add it on /settings.'];
        }

        if (self::callsRemaining() <= 0) {
            Setting::setValue('marketcheck_quota_blocked_at', now()->toIso8601String(), 'marketcheck');
            return [
                'error' => "MarketCheck monthly quota reached (" . self::quota() . " calls). Reset on /settings or wait for next month.",
                'quota_blocked' => true,
            ];
        }

        // Pessimistic increment so a hot loop can't slip past the limit.
        DB::transaction(function () {
            $row = Setting::firstOrCreate(
                ['key' => self::counterKey()],
                ['value' => '0', 'group' => 'marketcheck']
            );
            $row->update(['value' => (string) ((int) $row->value + 1)]);
        });

        $url = rtrim(config('services.marketcheck.api_url'), '/') . $path;
        try {
            $resp = Http::timeout(15)
                ->acceptJson()
                ->get($url, array_merge($query, ['api_key' => $key]));

            if (!$resp->successful()) {
                Log::warning("MarketCheck non-2xx", [
                    'path'   => $path,
                    'status' => $resp->status(),
                    'body'   => substr($resp->body(), 0, 500),
                ]);
                return ['error' => "MarketCheck HTTP {$resp->status()}", 'body' => $resp->body()];
            }
            return $resp->json() ?? ['error' => 'Empty response'];
        } catch (\Throwable $e) {
            // cURL puts the full URL (including api_key in the query string)
            // into its error text — scrub before logging or returning.
            $msg = preg_replace('/api_key=[^&\s)]+/', 'api_key=[redacted]', $e->getMessage());
            Log::error("MarketCheck call failed", ['path' => $path, 'msg' => $msg]);
            return ['error' => 'MarketCheck request failed: ' . $msg];
        }
    }
}
