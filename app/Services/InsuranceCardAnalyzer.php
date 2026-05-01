<?php

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Vision-based auto-insurance card / declaration-page OCR. Same pattern as
 * VehicleDamageAnalyzer + DriverLicenseAnalyzer.
 *
 * We extract the fields the rental agreement and any future claim need:
 * carrier, policy number, NAIC code, named insured, effective + expiration
 * dates, and (if the dec page shows them) coverage limits.
 */
class InsuranceCardAnalyzer
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
     * @return array{success:bool, fields?:array, error?:string, raw?:string}
     */
    public function extract(string $imagePath): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Anthropic API key not configured'];
        }

        $abs = Storage::disk('public')->path($imagePath);
        if (!file_exists($abs)) {
            return ['success' => false, 'error' => 'Insurance image not found'];
        }

        try {
            $response = $this->client->messages()->create([
                'model'      => 'claude-opus-4-7',
                'max_tokens' => 1024,
                'messages'   => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Read the auto-insurance ID card or declaration page shown. Return ONLY a JSON object — no prose, no fences:
{
  "carrier": string|null,
  "policy_number": string|null,
  "naic": string|null,
  "named_insured": string|null,
  "effective_date": string|null (YYYY-MM-DD),
  "expiration_date": string|null (YYYY-MM-DD),
  "vehicles": [string] (year/make/model strings if listed),
  "coverages": {
    "liability_bi_per_person": string|null,
    "liability_bi_per_accident": string|null,
    "liability_pd": string|null,
    "uninsured_motorist": string|null,
    "comprehensive_deductible": string|null,
    "collision_deductible": string|null
  },
  "agent_phone": string|null,
  "confidence": number (0..1)
}
Use null for fields you cannot read confidently. Do not guess. Empty array for "vehicles" if none listed.'],
                        ['type' => 'image', 'source' => [
                            'type'       => 'base64',
                            'media_type' => mime_content_type($abs) ?: 'image/jpeg',
                            'data'       => base64_encode(file_get_contents($abs)),
                        ]],
                    ],
                ]],
            ]);

            $text = $response->content[0]->text ?? '';
            $fields = $this->extractJson($text);

            if (!$fields) {
                return ['success' => false, 'error' => 'Could not parse model response', 'raw' => $text];
            }
            return ['success' => true, 'fields' => $fields];
        } catch (\Throwable $e) {
            Log::error('Insurance OCR failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function extractJson(string $text): ?array
    {
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
