<?php

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Vision-based driver-license OCR. Mirrors VehicleDamageAnalyzer's pattern —
 * Claude Sonnet 4.6, base64-encoded image, prompt asks for strict JSON.
 *
 * The model returns whatever fields are clearly legible. We don't fail the
 * upload if a field is missing; the worker can still type it in.
 */
class DriverLicenseAnalyzer
{
    private Client $client;

    public function __construct()
    {
        $this->client = \Anthropic::client(config('services.anthropic.api_key', ''));
    }

    public function isConfigured(): bool
    {
        return !empty(config('services.anthropic.api_key'));
    }

    /**
     * @param  string  $frontPath  Storage path on the public disk.
     * @param  string|null  $backPath  Optional back-of-license image.
     * @return array{success:bool, fields?:array, error?:string, raw?:string}
     */
    public function extract(string $frontPath, ?string $backPath = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Anthropic API key not configured'];
        }

        $front = Storage::disk('public')->path($frontPath);
        if (!file_exists($front)) {
            return ['success' => false, 'error' => 'Front-of-license image not found'];
        }

        $content = [
            ['type' => 'text', 'text' => 'Read the U.S. driver\'s license shown. Return ONLY a JSON object — no prose, no fences:
{
  "license_number": string|null,
  "first_name": string|null,
  "last_name": string|null,
  "middle_name": string|null,
  "date_of_birth": string|null (YYYY-MM-DD),
  "expiration_date": string|null (YYYY-MM-DD),
  "issue_date": string|null (YYYY-MM-DD),
  "state": string|null (2-letter postal code),
  "address": string|null,
  "city": string|null,
  "zip": string|null,
  "class": string|null,
  "endorsements": string|null,
  "restrictions": string|null,
  "sex": string|null ("M"|"F"|"X"),
  "is_real_id": boolean|null,
  "confidence": number (0..1)
}
Use null for any field you cannot read confidently. Do not guess.'],
            ['type' => 'image', 'source' => $this->imageSource($front)],
        ];

        if ($backPath) {
            $back = Storage::disk('public')->path($backPath);
            if (file_exists($back)) {
                $content[] = ['type' => 'text', 'text' => 'Back of the same license:'];
                $content[] = ['type' => 'image', 'source' => $this->imageSource($back)];
            }
        }

        try {
            $response = $this->client->messages()->create([
                'model'      => 'claude-opus-4-7',
                'max_tokens' => 1024,
                'messages'   => [['role' => 'user', 'content' => $content]],
            ]);

            $text = $response->content[0]->text ?? '';
            $fields = $this->extractJson($text);

            if (!$fields) {
                return ['success' => false, 'error' => 'Could not parse model response', 'raw' => $text];
            }
            return ['success' => true, 'fields' => $fields];
        } catch (\Throwable $e) {
            Log::error('DL OCR failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function imageSource(string $absolutePath): array
    {
        return [
            'type'       => 'base64',
            'media_type' => mime_content_type($absolutePath) ?: 'image/jpeg',
            'data'       => base64_encode(file_get_contents($absolutePath)),
        ];
    }

    private function extractJson(string $text): ?array
    {
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) return $decoded;
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
