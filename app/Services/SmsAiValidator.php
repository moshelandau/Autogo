<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Per-step answer validator. Called by LeaseApplicationBot before saving
 * each customer reply to session.collected. Decides:
 *
 *   accept  — answer matches what was asked → save the parsed value
 *   skip    — customer said "skip"/"don't want to"/"refuse" → record empty + advance
 *   reject  — answer doesn't match the question → re-prompt with hint
 *   escalate— customer is hostile/confused; flag for staff
 *
 * If Anthropic isn't configured, returns accept-as-is so the bot still works.
 * Decisions are cached briefly (15s) per step+answer to avoid double-billing
 * on retries.
 */
class SmsAiValidator
{
    /** Steps where validation is overkill (yes/no, image, free text) — skip the AI call. */
    private const SKIP_VALIDATION_KEYS = [
        'has_coapplicant', 'has_insurance', 'has_photos', 'is_insurance_claim',
        'rental_needed', 'wheels_turn', 'urgency', 'situation', 'damage_area',
        'pickup_location', 'dropoff_location', 'vehicle', 'vehicle_preference',
        'vehicle_interest', 'label', 'preferred_drop_off', 'own_or_rent',
        'co_own_or_rent',
    ];

    public function validate(string $stepKey, string $promptShown, string $rawAnswer): array
    {
        $raw = trim($rawAnswer);

        // Common-case shortcuts: customer types skip / refuse
        $lc = strtolower($raw);
        if (in_array($lc, ['skip', 'pass', 'na', 'n/a', 'none', "don't have", 'dont have', 'refuse', "won't say", 'wont say'], true)) {
            return ['action' => 'skip', 'parsed' => '', 'message' => "OK, skipping."];
        }

        // Cheap fields → accept as-is
        if (in_array($stepKey, self::SKIP_VALIDATION_KEYS, true) || $raw === '') {
            return ['action' => 'accept', 'parsed' => $raw, 'message' => ''];
        }

        if ((string) \App\Models\Setting::getValue('ai_validator_disabled') === '1') {
            return ['action' => 'accept', 'parsed' => $raw, 'message' => ''];
        }
        if (empty(config('services.anthropic.api_key'))) {
            return ['action' => 'accept', 'parsed' => $raw, 'message' => ''];
        }

        $cacheKey = 'sms_validator:' . md5($stepKey . '|' . $raw);
        return Cache::remember($cacheKey, 15, fn () => $this->callModel($stepKey, $promptShown, $raw));
    }

    private function callModel(string $stepKey, string $promptShown, string $raw): array
    {
        $prompt = <<<TXT
You are validating a single answer in an SMS application form.

The bot asked: "{$promptShown}"
The expected field is: {$stepKey}
The customer replied: "{$raw}"

Decide one of:
  accept   — the answer is a valid {$stepKey}. Output the cleaned/parsed value.
  reject   — the answer doesn't match (e.g. "huh?", random words, wrong format). Output a 1-sentence re-prompt explaining what's needed.
  skip     — the customer is refusing or doesn't have it ("skip", "don't want", "no clue", "refuse", "not now").
  escalate — the customer is upset/confused/hostile and needs a human.

Output VALID JSON ONLY, no prose:
  {"action":"accept","parsed":"<cleaned value>"}
  {"action":"reject","message":"<short re-ask, e.g. 'Please reply with your DOB in MM/DD/YYYY.'>"}
  {"action":"skip","message":"<short ack, e.g. 'OK, skipping.'>"}
  {"action":"escalate","message":"<short ack, e.g. 'A team member will follow up.'>"}
TXT;

        try {
            $resp = app(\App\Services\AiClient::class)->messages([
                'model'       => (string) (\App\Models\Setting::getValue('ai_validator_model') ?: 'claude-haiku-4-5'),
                'max_tokens'  => 150,
                'temperature' => 0,
                'system'      => 'You are a strict form validator. Output JSON only.',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (!is_array($data) || !isset($data['action'])) {
                Log::warning('SmsAiValidator: bad JSON', ['raw' => $text]);
                return ['action' => 'accept', 'parsed' => $raw, 'message' => ''];
            }
            return [
                'action'  => in_array($data['action'], ['accept', 'reject', 'skip', 'escalate'], true) ? $data['action'] : 'accept',
                'parsed'  => (string) ($data['parsed'] ?? $raw),
                'message' => substr((string) ($data['message'] ?? ''), 0, 200),
            ];
        } catch (\Throwable $e) {
            Log::warning('SmsAiValidator call failed', ['error' => $e->getMessage()]);
            return ['action' => 'accept', 'parsed' => $raw, 'message' => ''];
        }
    }
}
