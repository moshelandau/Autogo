<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Reservation;
use App\Services\AgreementRevisionService;
use Illuminate\Support\Carbon;

class ReservationObserver
{
    public function __construct(private readonly AgreementRevisionService $revisions) {}

    /**
     * Apply weekend credit BEFORE save: if the rental's date range
     * spans Friday → Monday (inclusive, 3 nights), apply a $20 credit.
     */
    public function saving(Reservation $r): void
    {
        if (!$r->pickup_date || !$r->return_date) return;

        $p = Carbon::parse($r->pickup_date);
        $ret = Carbon::parse($r->return_date);

        // Check each calendar day in [pickup, return-1] to see if it includes
        // a Fri + Sat + Sun block (the 3 weekend nights we credit).
        $days = [];
        for ($d = $p->copy()->startOfDay(); $d->lt($ret->copy()->startOfDay()->addDay()); $d->addDay()) {
            $days[] = $d->dayOfWeek; // 0=Sun ... 5=Fri, 6=Sat
        }
        $hasFri = in_array(Carbon::FRIDAY,   $days, true);
        $hasSat = in_array(Carbon::SATURDAY, $days, true);
        $hasSun = in_array(Carbon::SUNDAY,   $days, true);
        $qualifies = $hasFri && $hasSat && $hasSun;

        // Store on notes/metadata; actual ledger change: bump discount_amount by $20 for each weekend (capped 1 per rental in MVP)
        $existingDiscount = (float) ($r->discount_amount ?? 0);
        $hadWeekendCredit = str_contains((string) $r->notes, '[Weekend $20 credit applied]');

        if ($qualifies && !$hadWeekendCredit) {
            $r->discount_amount = $existingDiscount + 20;
            $r->notes = trim(($r->notes ?? '') . "\n[Weekend $20 credit applied]");
        } elseif (!$qualifies && $hadWeekendCredit) {
            // Dates changed and no longer qualify → remove the credit we added
            $r->discount_amount = max(0, $existingDiscount - 20);
            $r->notes = trim(str_replace('[Weekend $20 credit applied]', '', (string) $r->notes));
        }
    }

    /**
     * Generate agreement-revision snapshots on key state changes.
     */
    public function created(Reservation $r): void
    {
        $this->revisions->snapshot($r, 'reservation_created', 'rental_agreement');
    }

    public function updated(Reservation $r): void
    {
        // Only snapshot on meaningful state transitions
        $dirtyKeys = array_intersect(array_keys($r->getChanges()), [
            'status', 'vehicle_id', 'pickup_date', 'return_date',
            'daily_rate', 'total_price', 'total_paid', 'outstanding_balance',
            'actual_pickup_date', 'actual_return_date',
        ]);
        if (empty($dirtyKeys)) return;

        $action = match (true) {
            in_array('actual_return_date', $dirtyKeys, true) => 'return',
            in_array('actual_pickup_date', $dirtyKeys, true) => 'pickup',
            in_array('status', $dirtyKeys, true)              => match ($r->status) {
                'rental'    => 'rented',
                'completed' => 'completed',
                default     => 'manual_change',
            },
            in_array('vehicle_id', $dirtyKeys, true)          => 'vehicle_assigned',
            default                                            => 'manual_change',
        };

        $docType = in_array($action, ['return', 'completed'], true) ? 'return_receipt' : 'rental_agreement';
        $this->revisions->snapshot($r, $action, $docType);
    }
}
