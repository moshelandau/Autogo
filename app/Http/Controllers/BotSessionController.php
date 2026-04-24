<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LeaseApplicationSession;
use Inertia\Inertia;

class BotSessionController extends Controller
{
    /**
     * List every bot session — active, completed, aborted — so staff can
     * follow up on incomplete applications.
     */
    public function index()
    {
        $sessions = LeaseApplicationSession::query()
            ->with(['customer:id,first_name,last_name', 'deal:id,deal_number,stage'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($s) {
                $collected = $s->collected ?? [];
                $name = trim(
                    ($collected['first_name'] ?? $s->customer?->first_name ?? '')
                    . ' ' .
                    ($collected['last_name']  ?? $s->customer?->last_name  ?? '')
                );
                $status = $s->aborted_at ? 'aborted'
                    : ($s->completed_at
                        ? ($this->needsAttention($s) ? 'urgent' : 'completed')
                        : (($s->last_inbound_at && $s->last_inbound_at->lt(now()->subDays(2))) ? 'stalled' : 'active'));

                return [
                    'id'              => $s->id,
                    'phone'           => $s->phone,
                    'flow'            => $s->flow,
                    'current_step'    => $s->current_step,
                    'name'            => $name ?: '(unknown)',
                    'customer_id'     => $s->customer_id,
                    'deal_id'         => $s->deal_id,
                    'deal_number'     => $s->deal?->deal_number,
                    'progress_pct'    => $this->progressPct($s->flow, $s->current_step, $collected),
                    'status'          => $status,
                    'started_at'      => $s->created_at,
                    'last_inbound_at' => $s->last_inbound_at,
                    'completed_at'    => $s->completed_at,
                    'aborted_at'      => $s->aborted_at,
                ];
            });

        return Inertia::render('Bot/Sessions', ['sessions' => $sessions]);
    }

    public function show(LeaseApplicationSession $session)
    {
        $session->load(['customer', 'deal']);
        return Inertia::render('Bot/Session', [
            'session' => $session,
            'collected' => $session->collected ?? [],
        ]);
    }

    /**
     * Force-finalize a session — staff fills in the gaps and we go straight
     * to creating the Deal / Reservation / OfficeTask. Useful when a customer
     * abandoned the bot mid-flow but staff has the rest by phone.
     */
    public function finalize(LeaseApplicationSession $session)
    {
        if ($session->completed_at) {
            return back()->with('error', 'Already completed.');
        }
        try {
            // Use reflection to call the private finalize() on LeaseApplicationBot
            $bot = app(\App\Services\LeaseApplicationBot::class);
            $ref = new \ReflectionMethod($bot, 'finalize');
            $ref->setAccessible(true);
            $ref->invoke($bot, $session);
            return back()->with('success', 'Intake finalized.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Finalize failed: ' . $e->getMessage());
        }
    }

    public function abort(LeaseApplicationSession $session)
    {
        if ($session->completed_at || $session->aborted_at) {
            return back()->with('error', 'Session already closed.');
        }
        $session->update(['aborted_at' => now()]);
        return back()->with('success', 'Intake marked as aborted.');
    }

    /**
     * "Needs attention" = the customer finished telling the bot everything,
     * but no staff member has picked up the resulting Deal/Reservation/Task yet.
     */
    private function needsAttention(\App\Models\LeaseApplicationSession $s): bool
    {
        if (in_array($s->flow, ['lease', 'finance'], true)) {
            $deal = $s->deal;
            return $deal && empty($deal->salesperson_id) && $deal->stage === 'application';
        }
        // Rental, towing, bodyshop — completion implies it needs follow-up
        return true;
    }

    private function progressPct(string $flow, string $step, array $collected): int
    {
        $steps = $flow === 'lease' ? \App\Services\LeaseApplicationBot::STEPS_LEASE
                                   : \App\Services\LeaseApplicationBot::STEPS_RENTAL;
        $totalAnswerable = count(array_filter($steps, fn($s) => $s['key'] !== '__done__'));
        $answered = count(array_filter(array_keys($collected), fn($k) => !str_starts_with($k, '_')));
        return $totalAnswerable > 0 ? min(100, (int) round($answered / $totalAnswerable * 100)) : 0;
    }
}
