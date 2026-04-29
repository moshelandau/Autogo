<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\LeaseApplicationSession;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI guard for inbound SMS — the FIRST decision before anything else runs.
 *
 *   - action:  respond | silent | escalate
 *   - assignee: user_id (when escalating) | null
 *   - reason:  one-line explanation (logged for audit)
 *
 * Policy: default to SILENT on any uncertainty. The bot replies through Barry's
 * Telebroad API connection, so customers see the messages as coming from Barry
 * (a real person). A misplaced bot reply reads as Barry being confused or
 * unprofessional, which is worse than the bot staying out and letting staff
 * pick it up.
 *
 * Three things must ALL be true to "respond":
 *   1. RIGHT PLACE — the bot owns this thread (customer self-started a bot
 *      intake flow, or the bot has been the consistent AutoGo voice).
 *   2. RIGHT TIME — staff isn't actively engaged (no recent staff outbound
 *      that the customer is following up on).
 *   3. RIGHT QUESTION — the customer's reply clearly continues something
 *      visible in the thread that the bot itself said. Off-thread context
 *      ("my dad", "the closing", "as we discussed", phone calls, in-person
 *      visits) → silent.
 *
 * Decision is cached per phone+message for 60s so a fast burst of inbound
 * messages doesn't cost N model calls.
 */
class SmsAiRouter
{
    public function decide(string $fromPhone, string $incomingBody, ?Customer $customer): array
    {
        if ((string) \App\Models\Setting::getValue('ai_router_disabled') === '1') {
            // Operator explicitly disabled the guard — fall through to legacy guards.
            return ['action' => 'respond', 'assignee' => null, 'reason' => 'ai_router_disabled_setting'];
        }
        if (empty(config('services.anthropic.api_key'))) {
            // No AI configured — bias to silent so we don't post unsupervised replies.
            return ['action' => 'silent', 'assignee' => null, 'reason' => 'no_anthropic_key'];
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
            ->orderByDesc('id')->limit(20)
            ->get(['direction', 'body', 'created_at', 'user_id', 'attachments'])
            ->reverse()->values();

        // Resolve user names in one batch so each line can show STAFF (Aron).
        $userIds = $history->pluck('user_id')->filter()->unique()->values();
        $names = $userIds->isEmpty()
            ? collect()
            : User::whereIn('id', $userIds)->pluck('name', 'id');

        $thread = $history->map(function ($m) use ($names) {
            if ($m->direction === 'inbound') {
                return "[CUSTOMER · {$m->created_at}] " . trim((string) $m->body);
            }
            $label = $this->outboundLabel($m, $names);
            return "[{$label} · {$m->created_at}] " . trim((string) $m->body);
        })->implode("\n");

        $session = LeaseApplicationSession::where('phone', $fromPhone)
            ->whereNull('completed_at')->whereNull('aborted_at')
            ->latest('id')->first();

        $sessionLine = $session
            ? "ACTIVE BOT SESSION: flow={$session->flow}, current_step={$session->current_step}"
            : "NO ACTIVE BOT SESSION.";

        $staff = User::where('email', 'like', '%@autogoco.com')->orderBy('id')->get(['id', 'name'])
            ->map(fn ($u) => "  {$u->id}: {$u->name}")->implode("\n");

        $customerLine = $customer
            ? "Known customer #{$customer->id}: {$customer->first_name} {$customer->last_name}"
            : "Unknown sender (no customer record).";

        $prompt = <<<TXT
You are the SMS routing brain for AutoGo (a car rental + leasing + towing + bodyshop business in Monroe NY).

Decide whether the auto-bot should reply to this NEW inbound SMS. The bot replies through Barry's Telebroad API connection, so customers see replies as coming from "Barry" (a real person they may already know). A bot reply that misses context reads as Barry being confused — that's worse than no reply.

DEFAULT TO "silent". Only choose "respond" if ALL THREE are clearly true:

1. RIGHT PLACE — the bot owns this thread.
   ✓ The customer self-started a bot intake (texted an exact trigger word like "rental", "tow", "bodyshop", "lease") and there's an active bot session, OR
   ✓ The bot has been the consistent AutoGo voice in the recent thread (most recent outbound messages are labeled BOT).
   ✗ If any recent outbound is labeled STAFF (a real human at AutoGo), the bot does NOT own this thread.

2. RIGHT TIME — staff isn't actively engaged.
   This is contextual, not a fixed time window. Read the flow:
   ✗ If a STAFF message appears in the recent thread and the customer's new message is a follow-up to that staff message, stay silent — the human is on it.
   ✗ If the most recent AutoGo voice was STAFF and not BOT, stay silent.
   ✓ Only OK to respond if BOT was the last AutoGo voice and the customer is continuing the bot's conversation.

3. RIGHT QUESTION — you understand exactly what the customer is referring to.
   The customer thinks they're talking to a human (Barry). It's only OK to step in if you can see in the thread what they're referring to.
   ✓ Their message clearly answers/continues something the BOT itself said or asked.
   ✗ They reference something not in the thread: "my dad", "the closing", "as we discussed", "like you said earlier", a phone call, an in-person visit, paperwork being sent — the bot has no context for these and any reply will be wrong.
   ✗ Their message is ambiguous and could mean multiple things.

If ANY of the three is unclear → silent. Bias HEAVILY toward silent. It's never wrong to stay quiet; staff will see the inbound and handle it.

{$customerLine}
From: {$fromPhone}
{$sessionLine}

RECENT THREAD (oldest -> newest). Outbound messages are labeled BOT (auto-replies) or STAFF (Name) (real humans):
{$thread}

NEW INBOUND:
[CUSTOMER] {$incomingBody}

STAFF (assignee_id options):
{$staff}

Output VALID JSON ONLY, no prose. One of:
  {"action": "respond",  "reason": "<short — why all three are clearly satisfied>"}
  {"action": "silent",   "reason": "<short — which of the three failed>"}
  {"action": "escalate", "assignee_id": <id>|null, "reason": "<short — why a human is needed>"}

Pick "escalate" instead of "silent" if a human is clearly needed (complaint, dispute, urgency, payment issue, customer asking for a manager). Otherwise prefer "silent" when in doubt.
TXT;

        try {
            $resp = app(\App\Services\AiClient::class)->messages([
                'model'       => (string) (\App\Models\Setting::getValue('ai_router_model') ?: 'claude-sonnet-4-5'),
                'max_tokens'  => 200,
                'temperature' => 0,
                'system'      => 'You are a precise dispatcher. Output JSON only. Default to silent on any uncertainty.',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (!is_array($data) || !isset($data['action'])) {
                Log::warning('SmsAiRouter: bad JSON', ['raw' => $text]);
                return ['action' => 'silent', 'assignee' => null, 'reason' => 'bad_json_silent'];
            }
            return [
                'action'   => in_array($data['action'], ['respond', 'silent', 'escalate'], true) ? $data['action'] : 'silent',
                'assignee' => $data['assignee_id'] ?? null,
                'reason'   => substr((string) ($data['reason'] ?? ''), 0, 200),
            ];
        } catch (\Throwable $e) {
            Log::warning('SmsAiRouter call failed', ['error' => $e->getMessage()]);
            // Bias to silent on AI failure — a missed reply is recoverable, an
            // unsupervised wrong reply is not.
            return ['action' => 'silent', 'assignee' => null, 'reason' => 'exception_silent'];
        }
    }

    /** Label an outbound message as BOT or STAFF (Name) based on user_id + bot tag. */
    private function outboundLabel(CommunicationLog $m, \Illuminate\Support\Collection $names): string
    {
        $atts = is_array($m->attachments) ? $m->attachments : [];
        if (!empty($atts['_bot'])) return 'BOT';
        if ($m->user_id && $names->has($m->user_id)) return 'STAFF (' . $names->get($m->user_id) . ')';
        if ($m->user_id) return "STAFF (user #{$m->user_id})";
        // Outbound with no user and no _bot flag — sent from Telebroad UI/app
        // outside AutoGo. Treat as a real human message (could be Barry from
        // his phone, an admin from the Telebroad portal, etc.).
        return 'STAFF (external)';
    }
}
