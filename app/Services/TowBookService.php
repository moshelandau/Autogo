<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TowBook public API integration.
 * Docs: https://developer.towbook.com/
 *
 * Uses OAuth2 client_credentials grant. Register an app at
 * https://app.towbook.com/Settings/Integrations or via TowBook support
 * to get client_id + client_secret.
 */
class TowBookService
{
    private const BASE = 'https://api.towbook.com';
    private const TOKEN_URL = 'https://api.towbook.com/oauth/token';

    public function isConfigured(): bool
    {
        return !empty($this->clientId()) && !empty($this->clientSecret());
    }

    private function clientId(): ?string
    {
        return Setting::getValue('towbook_client_id', config('services.towbook.client_id'));
    }
    private function clientSecret(): ?string
    {
        return Setting::getValue('towbook_client_secret', config('services.towbook.client_secret'));
    }

    /**
     * Get an OAuth2 access token (cached for token TTL).
     */
    public function token(): ?string
    {
        return Cache::remember('towbook.access_token', now()->addMinutes(50), function () {
            $resp = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'scope'         => 'read write',
            ]);
            if (!$resp->successful()) {
                Log::warning('TowBook OAuth failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return null;
            }
            return $resp->json('access_token');
        });
    }

    public function get(string $path, array $params = []): array
    {
        if (!$this->isConfigured()) return [];
        $token = $this->token();
        if (!$token) return [];

        $resp = Http::withToken($token)
            ->acceptJson()
            ->retry(2, 1000)
            ->get(self::BASE . $path, $params);

        return $resp->successful() ? ($resp->json() ?? []) : [];
    }

    /**
     * Pull paginated calls between two dates.
     */
    public function calls(string $from, string $to, int $page = 1, int $perPage = 100): array
    {
        return $this->get('/v1/calls', [
            'from'     => $from,
            'to'       => $to,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Get a single call by id (full detail: customer, vehicle, addresses, charges).
     */
    public function call(int|string $id): array
    {
        return $this->get("/v1/calls/{$id}");
    }

    public function trucks(): array
    {
        return $this->get('/v1/trucks');
    }

    public function drivers(): array
    {
        return $this->get('/v1/drivers');
    }
}
