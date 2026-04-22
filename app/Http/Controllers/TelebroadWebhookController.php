<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\OperationalReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Receives Telebroad "Account-SMS" webhook fires.
 *
 * VERIFIED: Telebroad supports an Account-SMS webhook that fires on every
 *  SMS/MMS sent OR received on the account. The webhook URL is configured
 *  in the Telebroad portal with the trigger name appended:
 *      https://app.autogoco.com/api/telebroad/webhook/sms?secret=XXXX
 *  (configure in Settings -> Telebroad).
 *  Source: https://helpdesk.telebroad.com/support/solutions/articles/4000214102-webhook-integrations
 *
 * VERIFIED payload (observed from a real Telebroad fire 2026-04-21):
 *   {
 *     "id": 110167431,
 *     "direction": "received" | "sent",
 *     "startTime": "2026-04-21T18:47:50-04:00",
 *     "fromNumber": "18455008085",
 *     "toNumber":   "18457511133",
 *     "message":    "3rd",
 *     "media":      null | <mms data>,
 *     "webhookType":"AccountSMS",
 *     "secret":     "<our shared secret>"
 *   }
 * Raw payload is still stashed in attachments._raw for forensics.
 */
class TelebroadWebhookController extends Controller
{
    public function sms(Request $request)
    {
        // Shared-secret check (Telebroad webhooks have no built-in signing per their docs)
        $expected = (string) config('services.telebroad.webhook_secret');
        if ($expected === '' || $request->query('secret') !== $expected) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $payload = $request->all();

        // Verified Telebroad field names; fallbacks kept for safety.
        $from      = $this->pick($payload, ['fromNumber', 'from', 'sender', 'caller_id', 'snumber']);
        $to        = $this->pick($payload, ['toNumber',   'to',   'receiver', 'sms_line', 'dnumber']);
        $body      = $this->pick($payload, ['message',    'msgdata', 'body', 'text']);
        $direction = $this->pick($payload, ['direction',  'flow', 'type']);
        $messageId = $this->pick($payload, ['id',         'message_id', 'msg_id']);
        $timestamp = $this->pick($payload, ['startTime',  'timestamp', 'time', 'created_at']);

        // Telebroad sends direction = "received" (inbound) or "sent" (outbound).
        $isInbound = strtolower((string) $direction) === 'received'
            || !in_array(strtolower((string) $direction), ['sent', 'outbound', 'out'], true);

        // For outbound: skip ONLY if we already logged this exact message from
        // SmsController (matches by Telebroad message id). Otherwise it was
        // sent from somewhere else (Telebroad web UI, mobile app, another
        // device) and we want to record it so the thread is complete.
        if (!$isInbound) {
            if ($messageId && CommunicationLog::where('external_ref', $messageId)->exists()) {
                return response()->json(['ok' => true, 'note' => 'outbound_echo_skipped']);
            }
            // Also skip if we have a recent identical outbound to the same number
            // (handles the race where the webhook arrives before SmsController saves)
            $last10 = substr(preg_replace('/\D/', '', (string) $to), -10);
            $recentDup = CommunicationLog::query()
                ->where('channel', 'sms')->where('direction', 'outbound')
                ->where('to', 'ilike', "%{$last10}")
                ->where('body', $body)
                ->where('created_at', '>=', now()->subSeconds(15))
                ->exists();
            if ($recentDup) return response()->json(['ok' => true, 'note' => 'outbound_dup_within_15s']);
            // Otherwise: this came from outside AutoGo — log as outbound, no user_id
        }

        // Skip echoes of our own outbound to avoid duplicates
        if (!$isInbound && $messageId && CommunicationLog::where('external_ref', $messageId)->exists()) {
            return response()->json(['ok' => true, 'note' => 'duplicate_outbound_ignored']);
        }

        // Auto-link to customer by phone match
        $customer = $from ? $this->findCustomerByPhone($from) : null;

        // Parse MMS media — Telebroad sends `media` as a JSON-encoded string of URL array
        $mediaUrls = $this->parseMedia($payload['media'] ?? null);
        $attachments = ['_raw' => $payload];
        if (!empty($mediaUrls)) {
            $attachments['media'] = array_map(fn($url) => [
                'url'  => $url,
                'name' => basename(parse_url($url, PHP_URL_PATH) ?: 'attachment'),
                'mime' => $this->guessMime($url),
            ], $mediaUrls);
        }

        $log = CommunicationLog::create([
            'subject_type' => $customer ? Customer::class : null,
            'subject_id'   => $customer?->id,
            'customer_id'  => $customer?->id,
            'user_id'      => null,
            'channel'      => 'sms',
            'direction'    => $isInbound ? 'inbound' : 'outbound',
            'from'         => $from,
            'to'           => $to,
            'body'         => $body,
            'attachments'  => $attachments,
            'external_ref' => $messageId,
            'status'       => $isInbound ? 'received' : 'sent',
            'sent_at'      => $timestamp ? $this->parseTime($timestamp) : now(),
        ]);

        // Notify staff on inbound
        if ($isInbound) {
            $this->notifyStaff($log, $customer);
        }

        // AI router (layer 0) → rule-based safeguards (layer 1-3) → bot.
        if ($isInbound) {
            $decision = app(\App\Services\SmsAiRouter::class)->decide((string) $from, (string) $body, $customer);
            Log::info('SmsAiRouter decision', ['from' => $from, ...$decision]);

            if ($decision['action'] === 'silent') {
                // AI says don't reply (auto-responder, spam, customer asked to stop, etc.)
            } elseif ($decision['action'] === 'escalate') {
                // Human-only — assign the conversation if a specific staffer was named
                if (!empty($decision['assignee'])) {
                    \App\Models\CommunicationLog::query()
                        ->where('channel', 'sms')
                        ->where(function ($q) use ($from) {
                            $last10 = substr(preg_replace('/\D/', '', $from), -10);
                            $q->where('from', 'ilike', "%{$last10}")->orWhere('to', 'ilike', "%{$last10}");
                        })
                        ->update(['assigned_to' => $decision['assignee']]);
                }
            } elseif ($this->shouldRunBot((string) $from, (string) $body)) {
                try {
                    $bot = app(\App\Services\LeaseApplicationBot::class);
                    $mediaUrls = collect($mediaUrls ?? [])->all();
                    $bot->handleInbound((string) $from, (string) $body, $customer, $mediaUrls);
                } catch (\Throwable $e) {
                    Log::warning('Bot handleInbound failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['ok' => true, 'id' => $log->id]);
    }

    private function parseMedia($media): array
    {
        if (empty($media)) return [];
        if (is_string($media)) {
            $decoded = json_decode($media, true);
            return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        }
        if (is_array($media)) {
            return array_values(array_filter($media, 'is_string'));
        }
        return [];
    }

    private function guessMime(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'mp4'  => 'video/mp4',
            'mov'  => 'video/quicktime',
            'pdf'  => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * Decide whether the bot should respond to this inbound. Three guards:
     *   1. Global kill switch (Setting "bot_disabled" = "1")
     *   2. Auto-responder loop detection: if we've sent >= 3 outbound SMS
     *      to this phone in the last 10 minutes, stop responding (likely
     *      ping-ponging with a corporate auto-reply system)
     *   3. Auto-responder fingerprint in the body (common patterns)
     */
    private function shouldRunBot(string $from, string $body): bool
    {
        // 1. Global kill switch
        if ((string) \App\Models\Setting::getValue('bot_disabled') === '1') {
            \Log::info('SMS bot suppressed (kill switch)', ['from' => $from]);
            return false;
        }

        // 2. Loop guard — only suppress if we keep sending the SAME message.
        //    A normal multi-step bot conversation sends DIFFERENT prompts at
        //    each step (name → DOB → SSN → ...) so high outbound count alone
        //    is fine. A stuck loop = same body repeating because the
        //    customer/auto-responder keeps replying with something the bot
        //    can't advance past.
        $last10 = substr(preg_replace('/\D/', '', $from), -10);
        $recentBodies = \App\Models\CommunicationLog::query()
            ->where('channel', 'sms')->where('direction', 'outbound')
            ->where('to', 'ilike', "%{$last10}")
            ->where('created_at', '>=', now()->subMinutes(10))
            ->pluck('body');
        // Group by body and find any that repeats 3+ times
        $repeats = $recentBodies->countBy(fn ($b) => trim((string) $b))->filter(fn ($n) => $n >= 3);
        if ($repeats->isNotEmpty()) {
            \Log::warning('SMS bot suppressed (same message repeated — likely stuck/loop)', [
                'from' => $from, 'repeated_body' => substr($repeats->keys()->first() ?? '', 0, 80),
                'count' => $repeats->first(),
            ]);
            return false;
        }

        // 3. Fingerprint common auto-responder bodies
        $b = strtolower(trim($body));
        $fingerprints = [
            'auto-reply', 'auto reply', 'autoreply', 'automatic reply', 'automated message',
            'do not reply', 'do-not-reply', 'noreply', 'no-reply',
            'this is an automated', 'this mailbox is not monitored',
            'out of office', 'currently away', 'reply stop to',
            'msg&data rates', 'message and data rates', 'msg & data rates',
            'unable to receive sms', 'cannot receive text',
        ];
        foreach ($fingerprints as $f) {
            if (str_contains($b, $f)) {
                \Log::warning('SMS bot suppressed (auto-responder fingerprint)', ['from' => $from, 'matched' => $f]);
                return false;
            }
        }

        return true;
    }

    private function pick(array $payload, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] !== '' && $payload[$k] !== null) {
                return is_scalar($payload[$k]) ? (string) $payload[$k] : json_encode($payload[$k]);
            }
        }
        return null;
    }

    private function findCustomerByPhone(string $phone): ?Customer
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 10) {
            $last10 = substr($digits, -10);
            return Customer::where('phone', 'ilike', "%{$last10}")->first();
        }
        return null;
    }

    private function parseTime(string $ts): \Carbon\Carbon
    {
        try {
            // Try unix timestamp first
            if (ctype_digit($ts) && strlen($ts) >= 10) {
                return \Carbon\Carbon::createFromTimestamp((int) $ts);
            }
            return \Carbon\Carbon::parse($ts);
        } catch (\Throwable $e) {
            return now();
        }
    }

    private function notifyStaff(CommunicationLog $log, ?Customer $customer): void
    {
        try {
            $who = $customer ? "{$customer->first_name} {$customer->last_name}" : ($log->from ?? 'Unknown');
            $preview = mb_substr((string) $log->body, 0, 100);
            $url = $customer
                ? route('customers.show', $customer) . '#messages'
                : route('sms.index');

            $users = User::where('email', 'like', '%@autogoco.com')->get();
            Notification::send($users, new OperationalReminder(
                "📱 SMS from {$who}",
                $preview,
                $url
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify staff of inbound SMS', ['error' => $e->getMessage()]);
        }
    }
}
