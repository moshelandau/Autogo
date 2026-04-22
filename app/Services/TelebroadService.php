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
     * @param array $media Optional MMS attachments. Each entry can be either:
     *   - a URL string (we'll fetch+base64-encode it)
     *   - ['url' => '/path/or/full', 'name' => 'foo.jpg']
     *   - ['data' => '<base64>',     'name' => 'foo.jpg']
     *   - ['path' => '/abs/local/path.jpg', 'name' => 'foo.jpg']
     *
     * VERIFIED format (sniffed live from Telebroad's own web UI 2026-04-22):
     *   POST /send/sms
     *   Content-Type: application/json
     *   {
     *     "sms_line": "18457511133",
     *     "receiver": "18455008085",
     *     "msgdata":  "...",
     *     "media":    "[{\"name\":\"foo.png\",\"value\":\"<raw-base64>\"}]"
     *                  ^^^ a JSON-encoded STRING, not a real array
     *   }
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
                $items = [];
                foreach ($media as $m) {
                    [$base64, $name] = $this->resolveMediaItem($m);
                    if ($base64 === null) continue;
                    $items[] = ['name' => $name, 'value' => $base64];
                }
                if (!empty($items)) {
                    // Telebroad expects `media` as a JSON-encoded STRING, not a real array.
                    $payload['media'] = json_encode($items, JSON_UNESCAPED_SLASHES);
                }
            }
            $response = Http::withBasicAuth($this->username, $this->password)
                ->asJson()
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

    /**
     * Normalise one media item to [base64, name]. Returns [null, null] on failure.
     * Accepts string URL, or array with one of: data | url | path.
     */
    private function resolveMediaItem(mixed $m): array
    {
        if (is_string($m)) {
            $m = ['url' => $m];
        }
        if (!is_array($m)) return [null, null];

        $name = $m['name'] ?? null;

        // 1. Pre-supplied base64
        if (!empty($m['data'])) {
            $b64 = preg_replace('#^data:[^;]+;base64,#', '', (string) $m['data']);
            return [$b64, $name ?: ('attachment-' . substr(md5($b64), 0, 6))];
        }

        // 2. Local file path
        if (!empty($m['path']) && is_file($m['path'])) {
            $name = $name ?: basename($m['path']);
            return [base64_encode(file_get_contents($m['path'])), $name];
        }

        // 3. URL — try to fetch it. First map our public URL back to a local
        //    path (no Cloudflare round-trip), otherwise HTTP-fetch.
        if (!empty($m['url'])) {
            $url = (string) $m['url'];
            $name = $name ?: basename(parse_url($url, PHP_URL_PATH) ?: 'attachment');

            $localPath = $this->urlToLocalPath($url);
            if ($localPath && is_file($localPath)) {
                return [base64_encode(file_get_contents($localPath)), $name];
            }

            try {
                $bytes = @file_get_contents($url);
                if ($bytes !== false) return [base64_encode($bytes), $name];
            } catch (\Throwable) {}
        }

        return [null, null];
    }

    private function urlToLocalPath(string $url): ?string
    {
        $appUrl = (string) config('app.url');
        if ($appUrl && str_starts_with($url, rtrim($appUrl, '/') . '/storage/')) {
            $tail = substr($url, strlen(rtrim($appUrl, '/') . '/storage/'));
            $candidate = public_path('storage/' . $tail);
            return is_file($candidate) ? $candidate : null;
        }
        return null;
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
