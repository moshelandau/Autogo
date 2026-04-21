<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RentalPayment;
use App\Models\Reservation;

class PaymentBalanceObserver
{
    public function saved(RentalPayment $payment): void { $this->refreshFromPayment($payment); }
    public function deleted(RentalPayment $payment): void { $this->refreshFromPayment($payment); }

    private function refreshFromPayment(RentalPayment $payment): void
    {
        $reservation = $payment->reservation()->first();
        if ($reservation) $this->refreshReservationAndCustomer($reservation);
    }

    public function refreshReservationAndCustomer(Reservation $reservation): void
    {
        $totalPaid = (float) $reservation->payments()->where('status', 'completed')->sum('amount');
        $reservation->update([
            'total_paid'         => $totalPaid,
            'outstanding_balance'=> max(0, (float)$reservation->total_price - $totalPaid),
        ]);
        $reservation->customer?->refreshOutstandingBalance();
    }
}
