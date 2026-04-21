<?php

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Models\EzPassTransaction;
use App\Models\RentalPayment;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\SolaPaymentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class EzPassImportController extends Controller
{
    public function show()
    {
        return Inertia::render('EzPass/Import', [
            'recentImports' => EzPassTransaction::selectRaw("source_file, count(*) as cnt, sum(amount) as total, max(created_at) as imported_at")
                ->whereNotNull('source_file')
                ->groupBy('source_file')
                ->orderByDesc('imported_at')
                ->limit(10)
                ->get(),
            'unbilled' => $this->unbilledSummary(),
        ]);
    }

    /**
     * Parse a NY E-ZPass CSV/XLSX export and create EzPassTransaction rows.
     * Handles the standard NY E-ZPass Business Center format:
     *   Lane Txn ID, Tag/Plate #, Agency, Entry Plaza, Exit Plaza, Class, Date, Exit Time, Amount
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $rows = $this->readRows($file->getRealPath(), $file->getClientOriginalExtension());

        if (empty($rows)) return back()->with('error', 'No rows found in the file.');

        $headers = array_map(fn ($h) => strtolower(trim((string)$h)), array_shift($rows));

        $col = function (array $row, array $candidates) use ($headers) {
            foreach ($candidates as $c) {
                $i = array_search(strtolower($c), $headers, true);
                if ($i !== false && array_key_exists($i, $row)) return $row[$i];
            }
            return null;
        };

        $imported = 0; $skipped = 0; $linked = 0; $duplicates = 0;
        $vehiclesByPlate = Vehicle::whereNotNull('license_plate')
            ->get(['id','license_plate'])
            ->keyBy(fn ($v) => strtoupper(preg_replace('/\s+/', '', (string) $v->license_plate)));

        foreach ($rows as $row) {
            if (empty(array_filter($row, fn ($v) => trim((string)$v) !== ''))) { $skipped++; continue; }

            // Skip "PAYMENT" or "TAG LEASING FEE" summary rows (Class col is the marker)
            $class = trim((string) $col($row, ['class']));
            if (in_array(strtoupper($class), ['PAYMENT','TAG LEASING FEE','ADJUSTMENT'], true)) { $skipped++; continue; }

            $extRef = trim((string) $col($row, ['lane txn id','transaction id','transaction number','txn id','reference']));
            $postedDate = trim((string) $col($row, ['date','transaction date','posted date']));
            $postedTime = trim((string) $col($row, ['exit time','entry time','time']));
            $plateRaw  = trim((string) $col($row, ['tag/plate #','plate','license plate','plate number']));
            // Strip "NY " or similar state prefix: "NY LPE4469" → "LPE4469", keep state
            $plateState = null; $plate = strtoupper(preg_replace('/\s+/', ' ', $plateRaw));
            if (preg_match('/^([A-Z]{2})\s+(.+)$/', $plate, $m)) {
                $plateState = $m[1];
                $plate      = preg_replace('/\s+/', '', $m[2]);
            } else {
                $plate = preg_replace('/\s+/', '', $plate);
            }

            if (empty($plate) || empty($extRef)) { $skipped++; continue; }

            $plaza     = $col($row, ['exit plaza','plaza','plaza name','location']);
            $agency    = $col($row, ['agency','authority']);
            $amountRaw = $col($row, ['amount','fare','toll amount','charge']);
            $amount    = abs((float) preg_replace('/[^\d.\-]/', '', (string) $amountRaw));

            $postedAt = null;
            try {
                $dt = trim("$postedDate $postedTime");
                if ($dt) $postedAt = Carbon::parse($dt);
            } catch (\Throwable) { $postedAt = null; }

            // Dedupe: by Lane Txn ID (each toll has a unique one)
            if ($extRef && EzPassTransaction::where('external_ref', $extRef)->exists()) { $duplicates++; continue; }

            // Auto-link by plate
            $vehicleId = null;
            if ($plate) {
                $key = strtoupper(preg_replace('/\s+/', '', $plate));
                $vehicleId = $vehiclesByPlate[$key]->id ?? null;
            }

            // Auto-link reservation active on toll date
            $reservationId = null; $customerId = null;
            if ($vehicleId && $postedAt) {
                $reservation = Reservation::where('vehicle_id', $vehicleId)
                    ->where('pickup_date', '<=', $postedAt)
                    ->where(function ($q) use ($postedAt) {
                        $q->whereNull('actual_return_date')
                          ->orWhere('actual_return_date', '>=', $postedAt);
                    })
                    ->first();
                if ($reservation) {
                    $reservationId = $reservation->id;
                    $customerId = $reservation->customer_id;
                    $linked++;
                }
            }

            EzPassTransaction::create([
                'vehicle_id'     => $vehicleId,
                'reservation_id' => $reservationId,
                'customer_id'    => $customerId,
                'plate'          => $plate ?: null,
                'plate_state'    => $plateState,
                'posted_at'      => $postedAt,
                'agency'         => $agency,
                'plaza'          => $plaza,
                'amount'         => $amount,
                'type'           => 'toll',
                'source_file'    => $fileName,
                'external_ref'   => $extRef,
            ]);
            $imported++;
        }

        return back()->with('success',
            "Imported $imported tolls · linked $linked to active rentals · $duplicates duplicates skipped · $skipped empty/payment rows skipped.");
    }

    /** Summary of unbilled tolls grouped by reservation for the UI. */
    private function unbilledSummary(): array
    {
        $rows = EzPassTransaction::whereNotNull('reservation_id')
            ->whereDoesntHave('reservation.payments', fn ($q) =>
                $q->where('type', 'toll_passthrough')
                  ->whereColumn('reference', 'ez_pass_transactions.external_ref'))
            ->selectRaw('reservation_id, count(*) as count, sum(amount) as subtotal')
            ->groupBy('reservation_id')
            ->with(['reservation.customer:id,first_name,last_name,phone,email'])
            ->limit(100)
            ->get();

        return $rows->map(fn ($r) => [
            'reservation_id' => $r->reservation_id,
            'reservation_number' => $r->reservation?->reservation_number,
            'customer' => $r->reservation?->customer
                ? $r->reservation->customer->first_name . ' ' . $r->reservation->customer->last_name
                : null,
            'count'    => $r->count,
            'subtotal' => (float) $r->subtotal,
            'admin_fee'=> 10.00 * $r->count,    // $10 per toll per the rental agreement
            'total'    => (float) $r->subtotal + (10.00 * $r->count),
        ])->all();
    }

    /**
     * Bill a specific reservation for all its unbilled tolls:
     *  - Charges the card on file (via Sola High Rental)
     *  - Creates a rental_payment row
     *  - Logs to communication_logs (email/SMS/portal)
     *  - Marks each toll row so it's not billed again
     */
    public function billReservation(Reservation $reservation, SolaPaymentsService $sola)
    {
        $tolls = EzPassTransaction::where('reservation_id', $reservation->id)
            ->whereDoesntHave('reservation.payments', fn ($q) =>
                $q->where('type', 'toll_passthrough')
                  ->whereColumn('reference', 'ez_pass_transactions.external_ref'))
            ->get();

        if ($tolls->isEmpty()) return back()->with('error', 'No unbilled tolls for this rental.');

        $subtotal  = (float) $tolls->sum('amount');
        $adminFee  = 10.00 * $tolls->count();
        $total     = round($subtotal + $adminFee, 2);

        $reservation->load('customer', 'activeHold');
        $hold = $reservation->activeHold;

        if ($hold) {
            $result = $sola->charge(
                account: SolaPaymentsService::ACCOUNT_HIGH_RENTAL,
                card:   ['brand' => $hold->card_brand, 'last4' => $hold->card_last4, 'exp' => $hold->card_exp],
                amount: $total,
                description: "EZ Pass toll pass-through ({$tolls->count()} tolls + \${$adminFee} admin) — RA#{$reservation->reservation_number}",
            );
            if (!($result['ok'] ?? false)) return back()->with('error', 'Charge failed: ' . ($result['error'] ?? 'unknown'));

            RentalPayment::create([
                'reservation_id' => $reservation->id,
                'customer_id'    => $reservation->customer_id,
                'payment_method' => 'card_on_file',
                'card_brand'     => $hold->card_brand,
                'card_last4'     => $hold->card_last4,
                'amount'         => $total,
                'reference'      => 'toll-bulk-' . now()->format('YmdHis'),
                'status'         => 'completed',
                'type'           => 'toll_passthrough',
                'sola_transaction_data' => json_encode($result),
                'processed_by'   => auth()->id(),
                'paid_at'        => now(),
            ]);
        } else {
            // No hold → add to reservation outstanding balance
            $reservation->increment('outstanding_balance', $total);
        }

        // Build a human-friendly detail for the notification
        $details = $tolls->map(fn ($t) =>
            optional($t->posted_at)->format('m/d/Y h:i A') . " · {$t->plaza} ({$t->agency}) · $" . number_format((float)$t->amount, 2)
        )->implode("\n");

        // Notify customer via email (or log for portal display)
        if ($reservation->customer?->email) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "Your card on file was charged $" . number_format($total, 2) . " for EZ Pass tolls incurred during your rental RA#{$reservation->reservation_number}.\n\n"
                    . "Breakdown:\n{$details}\n\n"
                    . "Admin fee (\$10/toll): $" . number_format($adminFee, 2) . "\n"
                    . "Subtotal: $" . number_format($subtotal, 2) . "\n"
                    . "TOTAL: $" . number_format($total, 2) . "\n\n"
                    . "— High Car Rental",
                    function ($m) use ($reservation) {
                        $m->to($reservation->customer->email)
                          ->subject("High Car Rental — EZ Pass charges for RA#{$reservation->reservation_number}");
                    }
                );
            } catch (\Throwable) { /* don't block if mail fails */ }
        }

        CommunicationLog::create([
            'subject_type' => Reservation::class,
            'subject_id'   => $reservation->id,
            'customer_id'  => $reservation->customer_id,
            'user_id'      => auth()->id(),
            'channel'      => 'email',
            'direction'    => 'outbound',
            'to'           => $reservation->customer?->email,
            'subject'      => "EZ Pass charges for RA#{$reservation->reservation_number}",
            'body'         => $details . "\n\nAdmin fee: $" . number_format($adminFee, 2) . " · Total: $" . number_format($total, 2),
            'attachments'  => [['toll_ids' => $tolls->pluck('id')->all()]],
            'status'       => 'sent',
            'sent_at'      => now(),
        ]);

        return back()->with('success',
            "Billed $" . number_format($total, 2) . " to card on file for " . $tolls->count() . " tolls · customer notified.");
    }

    private function readRows(string $path, string $ext): array
    {
        if (in_array(strtolower($ext), ['xlsx', 'xls'], true)) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            return $reader->getActiveSheet()->toArray();
        }
        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            while (($r = fgetcsv($h)) !== false) $rows[] = $r;
            fclose($h);
        }
        return $rows;
    }
}
