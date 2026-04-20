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
        $this->apiUrl = (string) config('services.telebroad.api_url', '');
        $this->username = (string) config('services.telebroad.username', '');
        $this->password = (string) config('services.telebroad.password', '');
        $this->fromNumber = (string) config('services.telebroad.phone_number', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->username) && !empty($this->password) && !empty($this->fromNumber);
    }

    public function sendSms(string $toNumber, string $message): array
    {
        if (!$this->isConfigured()) return ['success' => false, 'error' => 'Telebroad is not configured'];

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->asForm()
                ->post("{$this->apiUrl}/send/sms", [
                    'sms_line' => $this->formatPhoneNumber($this->fromNumber),
                    'receiver' => $this->formatPhoneNumber($toNumber),
                    'msgdata' => $message,
                ]);

            $responseData = ['http_status' => $response->status(), 'body' => $response->json() ?? $response->body()];
            $hasBodyError = $response->json('error') !== null;

            if ($response->successful() && !$hasBodyError) {
                return [
                    'success' => true,
                    'external_id' => $response->json('message_id') ?? $response->json('id'),
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
