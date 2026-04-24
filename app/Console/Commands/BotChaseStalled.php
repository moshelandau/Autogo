<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LeaseApplicationSession;
use App\Services\TelebroadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends ONE polite follow-up to bot intake sessions that have gone quiet
 * for 4+ hours mid-flow. Only chases ONCE per session (tracked on the
 * collected JSON) and never on completed/aborted sessions.
 *
 * Scheduled hourly. Safe to re-run — dedupe is built in.
 */
class BotChaseStalled extends Command
{
    protected $signature   = 'bot:chase-stalled {--dry-run}';
    protected $description = 'Nudge bot intake sessions that have gone quiet mid-flow';

    public function handle(): int
    {
        $svc = app(TelebroadService::class);
        $dryRun = (bool) $this->option('dry-run');

        $stalled = LeaseApplicationSession::query()
            ->whereNull('completed_at')->whereNull('aborted_at')
            ->where('current_step', '!=', '__intent__')
            ->where('last_inbound_at', '<=', now()->subHours(4))
            ->where('last_inbound_at', '>=', now()->subDays(3))   // give up after 3d
            ->get();

        $sent = 0;
        foreach ($stalled as $s) {
            $collected = $s->collected ?? [];
            if (!empty($collected['__chased_at__'])) continue;     // one nudge max

            $first = $collected['first_name'] ?? '';
            $hello = $first !== '' ? "Hi {$first} — " : "Hi — ";
            $msg   = match ($s->flow) {
                'lease', 'finance' => "{$hello}still want to wrap up your application? Reply with the next answer or text STOP if you'd like us to set this aside.",
                'rental'           => "{$hello}still want to set up that rental? Reply with the next answer when you're ready.",
                'towing'           => "{$hello}did you still need a tow? If yes, reply with where the vehicle is.",
                'bodyshop'         => "{$hello}still want that estimate? Reply with the next answer when you're ready.",
                default            => "{$hello}we're still here — reply when you're ready to keep going.",
            };

            $this->line("• #{$s->id} {$s->flow}/{$s->current_step} → {$s->phone}: " . substr($msg, 0, 60) . "…");
            if ($dryRun) continue;

            try {
                $svc->sendSms($s->phone, $msg);
                $collected['__chased_at__'] = now()->toIso8601String();
                $s->update(['collected' => $collected]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('BotChaseStalled send failed', ['session' => $s->id, 'error' => $e->getMessage()]);
            }
        }

        $verb = $dryRun ? 'would chase' : 'chased';
        $this->info("✓ {$verb} {$sent} stalled session(s).");
        return 0;
    }
}
