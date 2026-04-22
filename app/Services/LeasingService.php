<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deal;
use App\Models\DealQuote;
use App\Models\DealTask;
use App\Models\Lender;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class LeasingService
{
    public function __construct(private readonly AccountingService $accounting) {}

    // ── Dashboard / Reporting ──────────────────────────
    public function getDashboardStats(): array
    {
        return [
            'active_deals' => Deal::active()->count(),
            'new_today' => Deal::whereDate('created_at', today())->count(),
            'won_today' => Deal::whereDate('won_at', today())->count(),
            'lost_today' => Deal::whereDate('lost_at', today())->count(),
            'overdue_tasks' => DealTask::overdue()->count(),
            'due_today' => DealTask::incomplete()->whereDate('due_date', today())->count(),
            'stale_deals' => Deal::stale()->count(),
        ];
    }

    public function getDealsByStage(): array
    {
        $stages = [];
        foreach (Deal::STAGES as $stage) {
            if ($stage === 'lost') continue;
            $stages[$stage] = Deal::with(['customer', 'salesperson', 'lender', 'incompleteTasks'])
                ->where('stage', $stage)
                ->orderByDesc('updated_at')
                ->get();
        }
        return $stages;
    }

    // ── Deals ──────────────────────────────────────────
    public function getDeals(?string $stage = null, ?string $search = null): LengthAwarePaginator
    {
        return Deal::with(['customer', 'salesperson', 'lender', 'incompleteTasks'])
            ->when($stage, fn($q, $s) => $q->where('stage', $s))
            ->when($search, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('deal_number', 'ilike', "%{$search}%")
                       ->orWhere('vehicle_make', 'ilike', "%{$search}%")
                       ->orWhere('vehicle_model', 'ilike', "%{$search}%")
                       ->orWhereHas('customer', fn($q3) => $q3->search($search));
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(25);
    }

    public function createDeal(array $data): Deal
    {
        return DB::transaction(function () use ($data) {
            $deal = Deal::create(array_merge($data, [
                'deal_number' => Deal::generateDealNumber(),
                'salesperson_id' => $data['salesperson_id'] ?? auth()->id(),
                'deal_start_date' => now(),
            ]));

            $deal->generateTasksForStage();

            return $deal->load(['customer', 'tasks']);
        });
    }

    public function updateDeal(Deal $deal, array $data): Deal
    {
        $deal->update($data);
        return $deal->fresh(['customer', 'salesperson', 'lender', 'tasks', 'quotes']);
    }

    public function transitionDeal(Deal $deal, string $newStage): Deal
    {
        $deal->transitionTo($newStage);

        if ($newStage === 'complete' && $deal->profit) {
            $this->accounting->recordLeasingCommission(
                (float) $deal->profit,
                "Deal #{$deal->deal_number} - {$deal->customer->full_name} - {$deal->vehicle_display}",
                auth()->id(),
                Deal::class,
                $deal->id,
            );
        }

        return $deal->fresh(['customer', 'tasks']);
    }

    public function markDealLost(Deal $deal, ?string $reason = null): Deal
    {
        $deal->update([
            'stage' => 'lost',
            'lost_at' => now(),
            'lost_reason' => $reason,
        ]);
        return $deal;
    }

    // ── Quotes ─────────────────────────────────────────
    public function createQuote(Deal $deal, array $data): DealQuote
    {
        return $deal->quotes()->create(array_merge($data, [
            'created_by' => auth()->id(),
        ]));
    }

    public function selectQuote(DealQuote $quote): void
    {
        DB::transaction(function () use ($quote) {
            $quote->deal->quotes()->update(['is_selected' => false]);
            $quote->update(['is_selected' => true]);

            $quote->deal->update([
                'monthly_payment' => $quote->monthly_payment,
                'term' => $quote->term,
                'mileage_per_year' => $quote->mileage_per_year,
                'sell_price' => $quote->sell_price,
                'lender_id' => $quote->lender_id,
                'drive_off' => $quote->das,
            ]);
        });
    }

    // ── Tasks ──────────────────────────────────────────
    public function completeTask(DealTask $task): void
    {
        $task->markComplete();
    }

    public function getOverdueTasks(): Collection
    {
        return DealTask::with(['deal.customer', 'deal.salesperson'])
            ->overdue()
            ->orderBy('due_date')
            ->get();
    }

    // ── VIN Decode (NHTSA API) ─────────────────────────
    public function decodeVin(string $vin): array
    {
        try {
            $response = Http::get("https://vpic.nhtsa.dot.gov/api/vehicles/decodevin/{$vin}?format=json");

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'NHTSA API request failed'];
            }

            $results = collect($response->json('Results', []));

            $getValue = fn(string $var) => $results->firstWhere('Variable', $var)['Value'] ?? null;

            return [
                'success' => true,
                'data' => [
                    'year' => $getValue('Model Year'),
                    'make' => $getValue('Make'),
                    'model' => $getValue('Model'),
                    'trim' => $getValue('Trim'),
                    'body_class' => $getValue('Body Class'),
                    'drive_type' => $getValue('Drive Type'),
                    'fuel_type' => $getValue('Fuel Type - Primary'),
                    'engine' => $getValue('Displacement (L)') ? $getValue('Displacement (L)') . 'L' : null,
                    'transmission' => $getValue('Transmission Style'),
                    'doors' => $getValue('Doors'),
                    'msrp' => $getValue('Base Price ($)'),
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Lenders ────────────────────────────────────────
    public function getLenders(): Collection
    {
        return Lender::active()->get();
    }

    public function createLender(array $data): Lender
    {
        return Lender::create($data);
    }

    public function updateLender(Lender $lender, array $data): Lender
    {
        $lender->update($data);
        return $lender;
    }
}
