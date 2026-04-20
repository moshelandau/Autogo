<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Claim;
use App\Models\ClaimStep;
use App\Models\ClaimSupplement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClaimService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function getClaims(?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        return Claim::with(['customer', 'insuranceEntries', 'steps'])
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->when($search, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->whereHas('customer', fn($q3) =>
                        $q3->where('first_name', 'ilike', "%{$search}%")
                           ->orWhere('last_name', 'ilike', "%{$search}%"))
                       ->orWhereHas('insuranceEntries', fn($q3) =>
                        $q3->where('claim_number', 'ilike', "%{$search}%")
                           ->orWhere('insurance_company', 'ilike', "%{$search}%"))
                       ->orWhere('vehicle_make', 'ilike', "%{$search}%")
                       ->orWhere('vehicle_model', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function getDashboardStats(): array
    {
        return [
            'new' => Claim::where('status', 'new')->count(),
            'filed' => Claim::where('status', 'filed')->count(),
            'in_progress' => Claim::where('status', 'in_progress')->count(),
            'completed' => Claim::where('status', 'completed')->count(),
            'total_outstanding' => Claim::whereIn('status', ['new', 'filed', 'in_progress'])
                ->selectRaw('COALESCE(SUM(approved_amount), 0) - COALESCE(SUM(paid_amount), 0) as outstanding')
                ->value('outstanding') ?? 0,
        ];
    }

    public function createClaim(array $data): Claim
    {
        return DB::transaction(function () use ($data) {
            $claim = Claim::create(array_merge($data, [
                'status' => 'new',
                'created_by' => auth()->id(),
            ]));

            // Generate the 9 standard steps
            $claim->generateSteps();

            // Add insurance entries if provided
            if (!empty($data['insurance_entries'])) {
                foreach ($data['insurance_entries'] as $entry) {
                    $claim->insuranceEntries()->create($entry);
                }
            }

            return $claim->load(['customer', 'steps', 'insuranceEntries']);
        });
    }

    public function updateClaim(Claim $claim, array $data): Claim
    {
        $claim->update($data);
        return $claim->fresh(['customer', 'steps', 'insuranceEntries', 'supplements', 'comments']);
    }

    public function completeStep(ClaimStep $step): void
    {
        $step->markComplete();

        // Auto-update claim status based on steps
        $claim = $step->claim;
        $completedCount = $claim->steps()->where('is_completed', true)->count();
        $totalCount = $claim->steps()->count();

        if ($completedCount === $totalCount) {
            $claim->update(['status' => 'completed']);
        } elseif ($completedCount > 0 && $claim->status === 'new') {
            $claim->update(['status' => 'filed']);
        } elseif ($completedCount > 1) {
            $claim->update(['status' => 'in_progress']);
        }
    }

    public function uncompleteStep(ClaimStep $step): void
    {
        $step->markIncomplete();
        $claim = $step->claim;
        if ($claim->status === 'completed') {
            $claim->update(['status' => 'in_progress']);
        }
    }

    public function addInsuranceEntry(Claim $claim, array $data): void
    {
        $claim->insuranceEntries()->create($data);
    }

    public function addSupplement(Claim $claim, array $data): ClaimSupplement
    {
        $supplement = $claim->supplements()->create($data);

        // Update claim supplement total
        $claim->update([
            'supplement_amount' => $claim->supplements()->sum('amount'),
        ]);

        return $supplement;
    }

    public function addComment(Claim $claim, string $body): void
    {
        $claim->comments()->create([
            'body' => $body,
            'user_id' => auth()->id(),
        ]);
    }

    public function recordPayment(Claim $claim, float $amount, string $description): void
    {
        DB::transaction(function () use ($claim, $amount, $description) {
            $claim->increment('paid_amount', $amount);

            $this->accounting->recordInsuranceClaimPayment(
                $amount,
                "Claim payment - {$claim->customer->full_name} - {$description}",
                auth()->id(),
                Claim::class,
                $claim->id,
            );
        });
    }
}
