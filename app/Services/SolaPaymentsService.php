<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReservationHold;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sola Payments integration — powered by Cardknox Gateway JSON API.
 * Endpoint: https://x1.cardknox.com/gatewayjson
 *
 * Two merchants: AutoGo (xKey = services.sola.api_key) + High Car Rental
 * (xKey = services.sola.webhook_secret, repurposed Sola field for the second merchant).
 *
 * Command reference (Cardknox):
 *  - cc:save      → tokenize without charge (for card-on-file)
 *  - cc:auth      → authorize only (security deposit hold)
 *  - cc:capture   → capture a prior auth
 *  - cc:voidrefund→ release/void an auth
 *  - cc:sale      → one-step charge
 *  - cc:refund    → refund an existing sale
 *
 * Docs: https://kb.cardknox.com/api/
 */
class SolaPaymentsService
{
    public const ACCOUNT_AUTOGO        = 'autogo';
    public const ACCOUNT_HIGH_RENTAL   = 'high_rental';

    private const ENDPOINT = 'https://x1.cardknox.com/gatewayjson';

    public function isConfigured(string $account = self::ACCOUNT_AUTOGO): bool
    {
        return !empty($this->xKey($account));
    }

    /** Pick the right xKey for the account. */
    private function xKey(string $account): ?string
    {
        return $account === self::ACCOUNT_HIGH_RENTAL
            ? Setting::getValue('sola_webhook_secret', config('services.sola.webhook_secret'))
            : Setting::getValue('sola_api_key',        config('services.sola.api_key'));
    }

    /**
     * Core Cardknox call. $params is merged with common envelope fields.
     */
    private function call(string $account, array $params): array
    {
        $xKey = $this->xKey($account);
        if (!$xKey) return ['ok' => false, 'mock' => true, 'error' => "No xKey for $account"];

        $payload = array_merge([
            'xKey'             => $xKey,
            'xVersion'         => '5.0.0',
            'xSoftwareName'    => 'AutoGo',
            'xSoftwareVersion' => '1.0',
        ], $params);

        try {
            $resp = Http::acceptJson()->timeout(15)->asForm()->post(self::ENDPOINT, $payload);
            if (!$resp->successful()) {
                Log::warning('Cardknox HTTP error', ['status' => $resp->status(), 'account' => $account]);
                return ['ok' => false, 'http_status' => $resp->status(), 'error' => $resp->body()];
            }
            $body = $resp->json();
            $status = $body['xStatus'] ?? '';
            $ok = $status === 'Approved';
            return [
                'ok'      => $ok,
                'account' => $account,
                'status'  => $status,
                'error'   => $ok ? null : ($body['xError'] ?? 'Declined'),
                'ref_num' => $body['xRefNum'] ?? null,
                'token'   => $body['xToken'] ?? null,
                'data'    => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Cardknox exception', ['error' => $e->getMessage(), 'account' => $account]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Hold (auth-only) — always on High Rental ─────────────
    public function authorizeHold(array $card, float $amount, ?string $description = null): array
    {
        $account = self::ACCOUNT_HIGH_RENTAL;
        if (!$this->isConfigured($account)) return $this->mockAuth($amount, $account);

        $result = $this->call($account, array_merge([
            'xCommand'     => 'cc:authonly',
            'xAmount'      => number_format($amount, 2, '.', ''),
            'xDescription' => $description ?? 'Rental security deposit',
        ], $this->cardFields($card)));

        if (!$result['ok']) return $result;

        return $result + ['authorization_id' => $result['ref_num']];
    }

    public function captureHold(ReservationHold $hold, ?float $amount = null): array
    {
        $account = self::ACCOUNT_HIGH_RENTAL;
        if (!$this->isConfigured($account)) {
            $hold->update(['status' => 'captured', 'captured_at' => now()]);
            return ['ok' => true, 'mock' => true];
        }

        $result = $this->call($account, [
            'xCommand' => 'cc:capture',
            'xRefNum'  => $hold->sola_authorization_id,
            'xAmount'  => number_format($amount ?? (float)$hold->amount, 2, '.', ''),
        ]);
        if ($result['ok']) {
            $hold->update(['status' => 'captured', 'captured_at' => now()]);
        }
        return $result;
    }

    public function releaseHold(ReservationHold $hold): array
    {
        $account = self::ACCOUNT_HIGH_RENTAL;
        if (!$this->isConfigured($account)) {
            $hold->update(['status' => 'released', 'released_at' => now()]);
            return ['ok' => true, 'mock' => true];
        }

        $result = $this->call($account, [
            'xCommand' => 'cc:voidrelease',
            'xRefNum'  => $hold->sola_authorization_id,
        ]);
        if ($result['ok']) {
            $hold->update(['status' => 'released', 'released_at' => now()]);
        }
        return $result;
    }

    // ── Direct charge — account picked by operator ───────────
    public function charge(string $account, array $card, float $amount, ?string $description = null): array
    {
        if (!in_array($account, [self::ACCOUNT_AUTOGO, self::ACCOUNT_HIGH_RENTAL], true)) {
            return ['ok' => false, 'error' => 'Invalid account'];
        }
        if (!$this->isConfigured($account)) return $this->mockCharge($amount, $account);

        $result = $this->call($account, array_merge([
            'xCommand'     => 'cc:sale',
            'xAmount'      => number_format($amount, 2, '.', ''),
            'xDescription' => $description ?? 'AutoGo charge',
        ], $this->cardFields($card)));

        return $result + ['charge_id' => $result['ref_num'] ?? null];
    }

    /**
     * Tokenize a card on the chosen merchant via Cardknox cc:save.
     * Returns ['success'=>bool, 'token'=>string, 'brand'=>string, 'last4'=>string, 'exp'=>string].
     * The PAN flows through this method only — we never persist it.
     */
    public function saveCard(string $account, array $card): array
    {
        if (!$this->isConfigured($account)) return ['success' => false, 'error' => "No xKey for $account"];

        $r = $this->call($account, array_merge([
            'xCommand' => 'cc:save',
        ], $this->cardFields($card)));
        $d = $r['data'] ?? [];

        if (($d['xStatus'] ?? '') !== 'Approved' || empty($d['xToken'])) {
            return ['success' => false, 'error' => $d['xError'] ?? 'Tokenize failed', 'response' => $d];
        }

        return [
            'success' => true,
            'token'   => $d['xToken'],
            'brand'   => strtolower($d['xCardType'] ?? 'card'),
            'last4'   => substr(preg_replace('/\D/', '', $card['number'] ?? ''), -4),
            'exp'     => $card['exp'] ?? '',
            'response'=> $d,
        ];
    }

    /**
     * Test the Cardknox connection for an xKey. We don't want to actually charge
     * or tokenize a card, so we send a lookup-only command that is auth-checked.
     */
    public function test(string $account): array
    {
        if (!$this->isConfigured($account)) {
            return ['ok' => false, 'message' => 'No xKey configured for ' . ($account === 'high_rental' ? 'High Car Rental' : 'AutoGo')];
        }

        // Send cc:save with no card details. Cardknox will reject:
        //  - bad xKey  → xError contains "key" / "authentication"
        //  - good xKey → xError mentions missing card data (the auth itself was accepted)
        $r = $this->call($account, [
            'xCommand' => 'cc:save',
        ]);
        $d   = $r['data'] ?? [];
        $err = strtolower((string)($d['xError'] ?? ''));
        $name = $account === 'high_rental' ? 'High Car Rental' : 'AutoGo';

        $isAuthFailure = str_contains($err, 'key')
            || str_contains($err, 'authentication')
            || str_contains($err, 'unauthorized')
            || str_contains($err, 'invalid login');

        if ($isAuthFailure) {
            return ['ok' => false, 'message' => "✗ {$name} xKey rejected by Cardknox: " . ($d['xError'] ?? 'unknown')];
        }
        return ['ok' => true, 'message' => "✓ {$name} xKey accepted by Cardknox · endpoint: " . self::ENDPOINT];
    }

    /** Map our card dict to Cardknox field names. Supports token or raw PAN. */
    private function cardFields(array $card): array
    {
        if (!empty($card['token'])) {
            return ['xToken' => $card['token']];
        }
        $fields = [];
        if (!empty($card['number']))  $fields['xCardNum'] = $card['number'];
        if (!empty($card['exp']))     $fields['xExp']     = preg_replace('/\D/', '', $card['exp']);
        if (!empty($card['cvc']))     $fields['xCVV']     = $card['cvc'];
        if (!empty($card['last4']))   $fields['xCardNum'] = $card['number'] ?? null; // fallback: cannot charge w/ last4 alone
        return $fields;
    }

    // ── Mocks (dev only) ─────────────────────────────────────
    private function mockAuth(float $amount, string $account): array
    {
        return [
            'ok' => true, 'mock' => true, 'account' => $account,
            'authorization_id' => 'mock_auth_'.Str::random(10),
            'ref_num' => 'mock_'.Str::random(10),
            'data' => ['xAmount' => $amount, 'xStatus' => 'Approved (MOCK)'],
        ];
    }

    private function mockCharge(float $amount, string $account): array
    {
        return [
            'ok' => true, 'mock' => true, 'account' => $account,
            'charge_id' => 'mock_ch_'.Str::random(10),
            'ref_num' => 'mock_'.Str::random(10),
            'data' => ['xAmount' => $amount, 'xStatus' => 'Approved (MOCK)'],
        ];
    }
}
