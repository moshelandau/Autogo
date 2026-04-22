<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelebroadService
{
    private string $apiUrl;
    private string $username;
    private string $password;
    private string $fromNumber;

    public function __construct()
    {
        // Prefer .env, fall back to DB-backed Settings (Settings → Telebroad UI)
        $this->apiUrl     = $this->resolve('services.telebroad.api_url',     'telebroad_api_url')
                            ?: 'https://webserv.telebroad.com/api/teleconsole/rest';
        $this->username   = $this->resolve('services.telebroad.username',     'telebroad_username');
        $this->password   = $this->resolve('services.telebroad.password',     'telebroad_password');
        $this->fromNumber = $this->resolve('services.telebroad.phone_number', 'telebroad_phone_number');
    }

    private function resolve(string $configKey, string $settingKey): string
    {
        $v = (string) config($configKey, '');
        if ($v !== '') return $v;
        return (string) Setting::getValue($settingKey, '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->username) && !empty($this->password) && !empty($this->fromNumber);
    }

    /**
     * @param array $mediaUrls Optional MMS attachment URLs (verified via
     *   Telebroad helpdesk article 4000110801 — `media` is a JSON array).
     */
    public function sendSms(string $toNumber, string $message, array $mediaUrls = []): array
    {
        if (!$this->isConfigured()) return ['success' => false, 'error' => 'Telebroad is not configured'];

        try {
            $payload = [
                'sms_line' => $this->formatPhoneNumber($this->fromNumber),
                'receiver' => $this->formatPhoneNumber($toNumber),
                'msgdata'  => $message,
            ];
            if (!empty($mediaUrls)) {
                $payload['media'] = json_encode(array_values($mediaUrls));
            }
            $response = Http::withBasicAuth($this->username, $this->password)
                ->asForm()
                ->post("{$this->apiUrl}/send/sms", $payload);

            $responseData = ['http_status' => $response->status(), 'body' => $response->json() ?? $response->body()];
            $hasBodyError = $response->json('error') !== null;

            if ($response->successful() && !$hasBodyError) {
                return [
                    'success' => true,
                    'external_id' => $response->json('result') ?? $response->json('message_id') ?? $response->json('id'),
                    'response' => $responseData,
                ];
            }

            $errorMessage = $hasBodyError
                ? ($response->json('error.message') ?? json_encode($response->json('error')))
                : $response->body();

            Log::warning('Telebroad SMS failed', ['to' => $toNumber, 'status' => $response->status()]);
            return ['success' => false, 'error' => $errorMessage, 'response' => $responseData];
        } catch (\Throwable $e) {
            Log::error('Telebroad SMS exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function makeCall(string $toNumber): array
    {
        if (!$this->isConfigured()) return ['success' => false, 'error' => 'Telebroad is not configured'];

        $voiceSource = trim((string) Setting::getValue('telebroad_voice_source', ''));
        if (empty($voiceSource)) return ['success' => false, 'error' => 'Telebroad voice source is not configured'];

        try {
            $params = [
                'snumber' => $voiceSource,
                'dnumber' => $this->formatPhoneNumber($toNumber),
                'callerids' => $this->fromNumber,
                'answer1' => '1',
            ];

            $response = Http::withBasicAuth($this->username, $this->password)
                ->asForm()
                ->post("{$this->apiUrl}/send/call", $params);

            $responseData = ['http_status' => $response->status(), 'body' => $response->json() ?? $response->body()];
            $hasBodyError = $response->json('error') !== null;

            if ($response->successful() && !$hasBodyError) {
                return [
                    'success' => true,
                    'external_id' => $response->json('call_id') ?? $response->json('id'),
                    'response' => $responseData,
                ];
            }

            $errorMessage = $hasBodyError
                ? ($response->json('error.message') ?? json_encode($response->json('error')))
                : $response->body();

            Log::warning('Telebroad call failed', ['to' => $toNumber, 'status' => $response->status()]);
            return ['success' => false, 'error' => $errorMessage, 'response' => $responseData];
        } catch (\Throwable $e) {
            Log::error('Telebroad call exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function formatPhoneNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);
        return strlen($digits) === 10 ? '1' . $digits : $digits;
    }
}
