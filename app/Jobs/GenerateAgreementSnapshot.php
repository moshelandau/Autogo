<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\AgreementRevisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job that renders + stores a tamper-evident agreement PDF for a
 * reservation. Lives off the synchronous request path because DomPDF is
 * memory- and time-hungry (often 500MB+ and 1-3s per call).
 */
class GenerateAgreementSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public int $reservationId,
        public string $action,
        public string $documentType = 'rental_agreement',
    ) {}

    public function handle(AgreementRevisionService $service): void
    {
        $reservation = Reservation::find($this->reservationId);
        if (!$reservation) return;

        try {
            $service->snapshot($reservation, $this->action, $this->documentType);
        } catch (\Throwable $e) {
            Log::warning('Agreement snapshot job failed', [
                'reservation_id' => $this->reservationId,
                'action'         => $this->action,
                'doc'            => $this->documentType,
                'error'          => $e->getMessage(),
            ]);
            // Don't re-throw — snapshot failure must never block real work.
            // Job will be marked complete; staff can manually regenerate.
        }
    }
}
