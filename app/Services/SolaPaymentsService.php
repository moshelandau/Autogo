<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReservationHold;
use App\Models\RentalPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sola Payments integration. Wraps:
 *  - Authorize a hold (deposit)
 *  - Capture a previously authorized hold (turn it into a real charge)
 *  - Release / void a hold
 *  - Charge a card directly (token-based)
 *
 * If SOLA_API_KEY is missing, falls back to MOCK mode so the UI works
 * end-to-end during development. Mock responses set realistic ids.
 */
class SolaPaymentsService
{
    public const ACCOUNT_AUTOGO = 'autogo';
    public const ACCOUNT_HIGH_RENTAL = 'high_rental';

    public function isConfigured(string $account = self::ACCOUNT_AUTOGO): bool
    {
        return !empty($this->apiKey($account));
    }

    private function apiKey(string $account): ?string
    {
        // AutoGo = services.sola.api_key
        // High Rental = services.sola.webhook_secret (the user re-purposed this field since Sola UI offers only one key field)
        return $account === self::ACCOUNT_HIGH_RENTAL
            ? \App\Models\Setting::getValue('sola_webhook_secret', config('services.sola.webhook_secret'))
            : \App\Models\Setting::getValue('sola_api_key',        config('services.sola.api_key'));
    }

    private function base(): string
    {
        $env = config('services.sola.env', 'sandbox');
        return $env === 'live'
            ? 'https://api.sola-payments.com'
            : 'https://sandbox.sola-payments.com';
    }

    private function http(string $account = self::ACCOUNT_AUTOGO)
    {
        return Http::withToken($this->apiKey($account))->acceptJson();
    }

    // ── Hold (auth-only) — ALWAYS on High Rental account per business rule ──
    public function authorizeHold(array $card, float $amount, ?string $description = null): array
    {
        $account = self::ACCOUNT_HIGH_RENTAL;
        if (!$this->isConfigured($account)) return $this->mockAuth($amount, $account);

        $resp = $this->http($account)->post($this->base().'/v1/authorizations', [
            'amount'      => $amount,
            'currency'    => 'USD',
            'card'        => $card,
            'description' => $description ?? 'Rental security deposit',
        ]);

        if (!$resp->successful()) {
            Log::warning('Sola auth failed', ['status' => $resp->status(), 'body' => $resp->body(), 'account' => $account]);
            return ['ok' => false, 'error' => $resp->body()];
        }
        $d = $resp->json();
        return ['ok' => true, 'authorization_id' => $d['id'] ?? null, 'account' => $account, 'data' => $d];
    }

    public function captureHold(ReservationHold $hold, ?float $amount = null): array
    {
        // Holds always live on high_rental
        $account = self::ACCOUNT_HIGH_RENTAL;
        if (!$this->isConfigured($account)) {
            $hold->update(['status' => 'captured', 'captured_at' => now()]);
            return ['ok' => true, 'mock' => true];
        }
        $resp = $this->http($account)->post($this->base()."/v1/authorizations/{$hold->sola_authorization_id}/capture", [
            'amount' => $amount ?? $hold->amount,
        ]);
        if ($resp->successful()) {
            $hold->update(['status' => 'captured', 'captured_at' => now()]);
            return ['ok' => true, 'data' => $resp->json()];
        }
        return ['ok' => false, 'error' => $resp->body()];
    }

    public function releaseHold(ReservationHold $hold): array
    {
        $account = self::ACCOUNT_HIGH_RENTAL;
        if (!$this->isConfigured($account)) {
            $hold->update(['status' => 'released', 'released_at' => now()]);
            return ['ok' => true, 'mock' => true];
        }
        $resp = $this->http($account)->delete($this->base()."/v1/authorizations/{$hold->sola_authorization_id}");
        if ($resp->successful() || $resp->status() === 404) {
            $hold->update(['status' => 'released', 'released_at' => now()]);
            return ['ok' => true];
        }
        return ['ok' => false, 'error' => $resp->body()];
    }

    // ── Direct charge — account selected by operator (AutoGo or High Rental) ──
    public function charge(string $account, array $card, float $amount, ?string $description = null): array
    {
        if (!in_array($account, [self::ACCOUNT_AUTOGO, self::ACCOUNT_HIGH_RENTAL], true)) {
            return ['ok' => false, 'error' => 'Invalid account'];
        }
        if (!$this->isConfigured($account)) return $this->mockCharge($amount, $account);

        $resp = $this->http($account)->post($this->base().'/v1/charges', [
            'amount' => $amount, 'currency' => 'USD', 'card' => $card,
            'description' => $description ?? 'Rental payment',
        ]);
        if (!$resp->successful()) return ['ok' => false, 'error' => $resp->body()];
        $d = $resp->json();
        return ['ok' => true, 'charge_id' => $d['id'] ?? null, 'account' => $account, 'data' => $d];
    }

    // ── Mocks ────────────────────────────────────────────────
    private function mockAuth(float $amount, string $account): array
    {
        return [
            'ok' => true, 'mock' => true, 'account' => $account,
            'authorization_id' => 'auth_mock_'.Str::random(12),
            'data' => ['amount' => $amount, 'status' => 'authorized'],
        ];
    }
    private function mockCharge(float $amount, string $account): array
    {
        return [
            'ok' => true, 'mock' => true, 'account' => $account,
            'charge_id' => 'ch_mock_'.Str::random(12),
            'data' => ['amount' => $amount, 'status' => 'succeeded'],
        ];
    }
}
