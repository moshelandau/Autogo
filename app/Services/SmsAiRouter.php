<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI decision layer for inbound SMS. Looks at the recent conversation history
 * for this phone and decides:
 *
 *   - action:  respond | silent | escalate
 *   - assignee: user_id (when escalating to a specific staff member) | null
 *   - reason:  one-line explanation (logged for audit)
 *
 * Designed to sit BEFORE the existing rule-based bot + rate-limit/fingerprint
 * guards. If Claude isn't configured or the call fails, returns "respond" so
 * the existing fallback safeguards still apply.
 *
 * Decision is cached per phone for 60s so a fast burst of inbound messages
 * doesn't cost N model calls.
 */
class SmsAiRouter
{
    public function decide(string $fromPhone, string $incomingBody, ?Customer $customer): array
    {
        if ((string) \App\Models\Setting::getValue('ai_router_disabled') === '1') {
            return ['action' => 'respond', 'assignee' => null, 'reason' => 'ai_router_disabled_setting'];
        }
        if (empty(config('services.anthropic.api_key'))) {
            return ['action' => 'respond', 'assignee' => null, 'reason' => 'no_anthropic_key'];
        }

        $cacheKey = 'sms_ai_router:' . substr(preg_replace('/\D/', '', $fromPhone), -10) . ':' . md5($incomingBody);
        return Cache::remember($cacheKey, 60, function () use ($fromPhone, $incomingBody, $customer) {
            return $this->callModel($fromPhone, $incomingBody, $customer);
        });
    }

    private function callModel(string $fromPhone, string $incomingBody, ?Customer $customer): array
    {
        $last10 = substr(preg_replace('/\D/', '', $fromPhone), -10);
        $history = CommunicationLog::query()
            ->where('channel', 'sms')
            ->where(function ($q) use ($last10) {
                $q->where('from', 'ilike', "%{$last10}")->orWhere('to', 'ilike', "%{$last10}");
            })
            ->orderByDesc('id')->limit(20)->get(['direction', 'body', 'created_at'])
            ->reverse()->values();

        $thread = $history->map(function ($m) {
            $who = $m->direction === 'inbound' ? 'CUSTOMER' : 'AUTOGO';
            return "[{$who} · {$m->created_at}] " . trim((string) $m->body);
        })->implode("\n");

        $staff = User::where('email', 'like', '%@autogoco.com')->orderBy('id')->get(['id', 'name'])
            ->map(fn ($u) => "  {$u->id}: {$u->name}")->implode("\n");

        $customerLine = $customer
            ? "Known customer #{$customer->id}: {$customer->first_name} {$customer->last_name}"
            : "Unknown sender (no customer record).";

        $prompt = <<<TXT
You are the SMS routing brain for AutoGo (a car rental + leasing + towing + bodyshop business in Monroe NY).

Decide what to do with this NEW inbound SMS based on the recent conversation.

{$customerLine}
From: {$fromPhone}

RECENT THREAD (oldest -> newest):
{$thread}

NEW INBOUND:
[CUSTOMER] {$incomingBody}

STAFF (assignee_id options):
{$staff}

Output VALID JSON ONLY, no prose. One of:
  {"action": "respond", "reason": "<short>"}              -- our auto-bot should reply (lead gen, intake, normal flow)
  {"action": "silent",  "reason": "<short>"}              -- do not reply (auto-responder loop, spam, off-topic, vendor blast, customer told us to stop, etc.)
  {"action": "escalate","assignee_id": <id>|null, "reason": "<short>"}  -- a human should handle (complaint, complex question, dispute, payment issue, urgency)

Pick "silent" if the inbound is clearly machine-generated (auto-reply, do-not-reply, out-of-office, marketing blast, "stop", "unsubscribe").
Pick "escalate" with no assignee_id if a human is needed but you don't know who; pick a specific staff id only if context makes it obvious.
Otherwise pick "respond".
TXT;

        try {
            $resp = app(\App\Services\AiClient::class)->messages([
                'model'       => (string) (\App\Models\Setting::getValue('ai_router_model') ?: 'claude-3-5-sonnet-latest'),
                'max_tokens'  => 200,
                'temperature' => 0,
                'system'      => 'You are a precise dispatcher. Output JSON only.',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (!is_array($data) || !isset($data['action'])) {
                Log::warning('SmsAiRouter: bad JSON', ['raw' => $text]);
                return ['action' => 'respond', 'assignee' => null, 'reason' => 'bad_json_fallback'];
            }
            return [
                'action'   => in_array($data['action'], ['respond', 'silent', 'escalate'], true) ? $data['action'] : 'respond',
                'assignee' => $data['assignee_id'] ?? null,
                'reason'   => substr((string) ($data['reason'] ?? ''), 0, 200),
            ];
        } catch (\Throwable $e) {
            Log::warning('SmsAiRouter call failed', ['error' => $e->getMessage()]);
            return ['action' => 'respond', 'assignee' => null, 'reason' => 'exception_fallback'];
        }
    }
}
