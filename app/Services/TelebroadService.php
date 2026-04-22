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
     * @param array $media Optional MMS attachments. Either array of URL
     *   strings, or array of ['url' => ..., 'type' => 'image/jpeg', 'name'?].
     *   Telebroad expects a JSON array of objects with `url` + `type` (verified
     *   live 2026-04-22 — string-only array returns code 444).
     */
    public function sendSms(string $toNumber, string $message, array $media = []): array
    {
        if (!$this->isConfigured()) return ['success' => false, 'error' => 'Telebroad is not configured'];

        try {
            $payload = [
                'sms_line' => $this->formatPhoneNumber($this->fromNumber),
                'receiver' => $this->formatPhoneNumber($toNumber),
                'msgdata'  => $message,
            ];
            if (!empty($media)) {
                $payload['media'] = json_encode(array_values(array_map(function ($m) {
                    if (is_string($m)) {
                        return ['url' => $m, 'type' => $this->guessMimeFromUrl($m)];
                    }
                    return [
                        'url'  => $m['url']  ?? '',
                        'type' => $m['type'] ?? $m['mime'] ?? $this->guessMimeFromUrl($m['url'] ?? ''),
                    ];
                }, $media)));
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

    private function guessMimeFromUrl(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'mp4'  => 'video/mp4',
            'mov'  => 'video/quicktime',
            'mp3'  => 'audio/mpeg',
            'm4a'  => 'audio/mp4',
            'wav'  => 'audio/wav',
            'webm' => 'audio/webm',  // assume audio (recorded voice notes)
            'pdf'  => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function formatPhoneNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);
        return strlen($digits) === 10 ? '1' . $digits : $digits;
    }
}
