<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Pull rental-agreement PDFs for each historical reservation from HQ Rentals.
 *
 * Usage:
 *   php artisan hq:extract-leases             # all reservations missing a lease PDF
 *   php artisan hq:extract-leases --limit=50  # cap how many to fetch
 *   php artisan hq:extract-leases --reservation=123
 *
 * Requires .env / Settings:
 *   HQ_RENTALS_API_KEY
 *   HQ_RENTALS_SUBDOMAIN  (default: highrental)
 */
class ExtractHqLeaseAgreements extends Command
{
    protected $signature = 'hq:extract-leases {--limit=0} {--reservation=}';
    protected $description = 'Download rental-agreement PDF from HQ Rentals for each reservation';

    public function handle(): int
    {
        $apiKey   = config('services.hq_rentals.api_key');
        $subdomain= config('services.hq_rentals.subdomain', 'highrental');
        if (!$apiKey) { $this->error('HQ_RENTALS_API_KEY not set (see Settings → HQ Rentals).'); return 1; }

        $base = "https://{$subdomain}.us5.hqrentals.app/api/integration/v1";

        $query = Reservation::whereNull('lease_agreement_path')
            ->whereNotNull('hq_rentals_id');
        if ($r = $this->option('reservation')) $query->where('id', $r);
        if ($limit = (int) $this->option('limit')) $query->limit($limit);

        $reservations = $query->get();
        $this->info("Reservations to process: ".$reservations->count());

        $ok = 0; $fail = 0;
        foreach ($reservations as $reservation) {
            $hqId = $reservation->hq_rentals_id;

            // Try multiple plausible endpoints (HQ Rentals has versioned APIs)
            $candidates = [
                "{$base}/reservations/{$hqId}/contract.pdf",
                "{$base}/reservations/{$hqId}/agreement",
                "{$base}/reservations/{$hqId}/documents/contract",
            ];

            $saved = false;
            foreach ($candidates as $url) {
                try {
                    $resp = Http::withToken($apiKey)
                        ->withHeaders(['Accept' => 'application/pdf'])
                        ->timeout(15)
                        ->get($url);

                    if ($resp->successful() && str_contains($resp->header('Content-Type', ''), 'pdf')) {
                        $path = "reservations/{$reservation->id}/hq-lease-{$hqId}.pdf";
                        Storage::disk('public')->put($path, $resp->body());
                        $reservation->update(['lease_agreement_path' => $path]);
                        $ok++;
                        $saved = true;
                        $this->line("  ✓ {$reservation->reservation_number} ({$hqId}) → {$path}");
                        break;
                    }
                } catch (\Throwable $e) { /* try next candidate */ }
            }

            if (!$saved) { $fail++; $this->line("  ✗ {$reservation->reservation_number} ({$hqId}) — no PDF available"); }
        }

        $this->info("Done. Saved: {$ok} · Failed: {$fail}");
        return 0;
    }
}
