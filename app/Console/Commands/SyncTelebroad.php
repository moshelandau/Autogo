<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pull anything we may have missed from Telebroad — call history + recent
 * SMS — and back-fill missing rows in communication_logs.
 *
 * VERIFIED endpoints (live test 2026-04-23):
 *   GET /sms/conversations  → {result: [{id, sender, receiver, line,
 *                                        time (unix), msgdata, media, ...}]}
 *   GET /call/history       → {result: [{...}]}  (currently empty for our
 *                              account — handler tolerates either shape)
 *
 * De-duped by external_ref (Telebroad's message id / call id) so re-runs
 * are safe.
 *
 * Usage:
 *   php artisan telebroad:sync          # last 24 h
 *   php artisan telebroad:sync --hours=72
 *   php artisan telebroad:sync --dry-run
 *
 * Scheduled to run every 10 minutes (see routes/console.php).
 */
class SyncTelebroad extends Command
{
    protected $signature   = 'telebroad:sync {--hours=24} {--dry-run}';
    protected $description = 'Back-fill any SMS / call history we may have missed from Telebroad webhooks';

    public function handle(): int
    {
        $u = (string) (Setting::getValue('telebroad_username') ?: config('services.telebroad.username'));
        $p = (string) (Setting::getValue('telebroad_password') ?: config('services.telebroad.password'));
        $apiUrl = (string) (Setting::getValue('telebroad_api_url') ?: config('services.telebroad.api_url'));
        if (!$u || !$p) { $this->error('Telebroad credentials not configured'); return 1; }

        $cutoff = now()->subHours((int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $sms   = $this->syncSms($apiUrl, $u, $p, $cutoff, $dryRun);
        $calls = $this->syncCalls($apiUrl, $u, $p, $cutoff, $dryRun);

        $verb = $dryRun ? 'would back-fill' : 'back-filled';
        $this->info("✓ Done — {$verb} {$sms} SMS row(s), {$calls} call row(s).");
        return 0;
    }

    private function syncSms(string $apiUrl, string $u, string $p, \Carbon\Carbon $cutoff, bool $dryRun): int
    {
        $resp = Http::withBasicAuth($u, $p)->acceptJson()->timeout(30)->get("{$apiUrl}/sms/conversations");
        if (!$resp->successful()) { $this->warn("SMS list HTTP {$resp->status()}"); return 0; }
        $rows = (array) $resp->json('result', []);
        $added = 0;
        foreach ($rows as $r) {
            $msgId = (string) ($r['id'] ?? '');
            if ($msgId === '') continue;
            if (CommunicationLog::where('external_ref', $msgId)->exists()) continue;

            $when = isset($r['time']) ? \Carbon\Carbon::createFromTimestamp((int) $r['time']) : now();
            if ($when->lt($cutoff)) continue;

            $isInbound = strtolower((string) ($r['direction'] ?? 'in')) !== 'out';
            $from = (string) ($r['sender']   ?? '');
            $to   = (string) ($r['receiver'] ?? '');
            $body = (string) ($r['msgdata']  ?? '');

            $last10 = substr(preg_replace('/\D/', '', $isInbound ? $from : $to), -10);
            $customer = $last10 ? Customer::findByAnyPhone($last10) : null;

            $media = $r['media'] ?? null;
            $attach = ['_raw' => $r, '_synced' => true];
            if (!empty($media)) {
                $list = is_string($media) ? json_decode($media, true) : $media;
                if (is_array($list) && !empty($list)) {
                    $attach['media'] = array_map(fn ($u) => is_string($u)
                        ? ['url' => $u, 'name' => basename(parse_url($u, PHP_URL_PATH) ?: 'attachment')]
                        : $u, $list);
                }
            }

            if (!$dryRun) {
                CommunicationLog::create([
                    'subject_type' => $customer ? Customer::class : null,
                    'subject_id'   => $customer?->id,
                    'customer_id'  => $customer?->id,
                    'channel'      => 'sms',
                    'direction'    => $isInbound ? 'inbound' : 'outbound',
                    'from'         => $from ?: null,
                    'to'           => $to   ?: null,
                    'body'         => $body,
                    'attachments'  => $attach,
                    'external_ref' => $msgId,
                    'status'       => $isInbound ? 'received' : 'sent',
                    'sent_at'      => $when,
                    'created_at'   => $when,
                    'updated_at'   => now(),
                ]);
            }
            $added++;
        }
        return $added;
    }

    private function syncCalls(string $apiUrl, string $u, string $p, \Carbon\Carbon $cutoff, bool $dryRun): int
    {
        $resp = Http::withBasicAuth($u, $p)->acceptJson()->timeout(30)->get("{$apiUrl}/call/history");
        if (!$resp->successful()) { $this->warn("call/history HTTP {$resp->status()}"); return 0; }
        $rows = (array) $resp->json('result', []);
        $added = 0;
        foreach ($rows as $c) {
            $callId = (string) ($c['id'] ?? $c['uniqueid'] ?? $c['call_id'] ?? '');
            if ($callId === '') continue;
            if (CommunicationLog::where('external_ref', $callId)->where('channel', 'call')->exists()) continue;

            $when = isset($c['time']) ? \Carbon\Carbon::createFromTimestamp((int) $c['time'])
                : (isset($c['startTime']) ? \Carbon\Carbon::parse($c['startTime']) : now());
            if ($when->lt($cutoff)) continue;

            $direction = strtolower((string) ($c['direction'] ?? 'in')) === 'out' ? 'outbound' : 'inbound';
            $from = (string) ($c['caller'] ?? $c['fromNumber'] ?? $c['src'] ?? '');
            $to   = (string) ($c['callee'] ?? $c['toNumber']   ?? $c['dst'] ?? '');
            $duration = (int) ($c['duration'] ?? $c['billsec'] ?? 0);

            $last10 = substr(preg_replace('/\D/', '', $direction === 'inbound' ? $from : $to), -10);
            $customer = $last10 ? Customer::findByAnyPhone($last10) : null;

            $body = "📞 " . ucfirst($direction) . " call · " . ($duration > 0 ? gmdate('i:s', $duration) : 'no answer');

            if (!$dryRun) {
                CommunicationLog::create([
                    'subject_type' => $customer ? Customer::class : null,
                    'subject_id'   => $customer?->id,
                    'customer_id'  => $customer?->id,
                    'channel'      => 'call',
                    'direction'    => $direction,
                    'from'         => $from ?: null,
                    'to'           => $to   ?: null,
                    'body'         => $body,
                    'attachments'  => ['_raw' => $c, '_synced' => true, 'duration' => $duration],
                    'external_ref' => $callId,
                    'status'       => $duration > 0 ? 'completed' : 'missed',
                    'sent_at'      => $when,
                    'created_at'   => $when,
                    'updated_at'   => now(),
                ]);
            }
            $added++;
        }
        return $added;
    }
}
