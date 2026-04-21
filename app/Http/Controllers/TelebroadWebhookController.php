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

        // Skip echoes of our own outbound to avoid duplicates
        if (!$isInbound && $messageId && CommunicationLog::where('external_ref', $messageId)->exists()) {
            return response()->json(['ok' => true, 'note' => 'duplicate_outbound_ignored']);
        }

        // Auto-link to customer by phone match
        $customer = $from ? $this->findCustomerByPhone($from) : null;

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
            'attachments'  => ['_raw' => $payload],
            'external_ref' => $messageId,
            'status'       => $isInbound ? 'received' : 'sent',
            'sent_at'      => $timestamp ? $this->parseTime($timestamp) : now(),
        ]);

        // Notify staff on inbound
        if ($isInbound) {
            $this->notifyStaff($log, $customer);
        }

        return response()->json(['ok' => true, 'id' => $log->id]);
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
