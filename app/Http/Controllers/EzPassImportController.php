<?php

namespace App\Http\Controllers;

use App\Models\EzPassAccount;
use App\Models\EzPassTransaction;
use App\Models\Reservation;
use App\Models\Vehicle;
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
        ]);
    }

    /**
     * Parse a NY E-ZPass CSV/XLSX export and create EzPassTransaction rows.
     * Auto-links to the rental vehicle (by plate) and the reservation (if one
     * was active on the toll's date).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $rows = $this->readRows($file->getRealPath(), $file->getClientOriginalExtension());

        if (empty($rows)) {
            return back()->with('error', 'No rows found in the file.');
        }

        $headers = array_map('strtolower', array_map('trim', array_shift($rows)));

        $col = function (array $row, array $candidates) use ($headers) {
            foreach ($candidates as $c) {
                $i = array_search(strtolower($c), $headers, true);
                if ($i !== false) return $row[$i] ?? null;
            }
            return null;
        };

        $imported = 0; $skipped = 0; $linked = 0;
        $vehiclesByPlate = Vehicle::whereNotNull('license_plate')
            ->get(['id','license_plate'])->keyBy(fn ($v) => strtoupper(preg_replace('/\s+/', '', $v->license_plate)));

        foreach ($rows as $row) {
            if (empty(array_filter($row))) { $skipped++; continue; }

            $extRef = $col($row, ['transaction id', 'transaction number', 'txn id', 'reference']);
            $postedRaw = $col($row, ['posted date', 'transaction date', 'date', 'date/time', 'date time']);
            $plate     = strtoupper(trim((string) $col($row, ['plate', 'license plate', 'plate number', 'lic plate'])));
            $tagNumber = trim((string) $col($row, ['tag number', 'tag', 'transponder']));
            $plaza     = $col($row, ['plaza', 'plaza name', 'location', 'exit/plaza', 'point']);
            $agency    = $col($row, ['agency', 'authority']);
            $lane      = $col($row, ['lane', 'lane number']);
            $amountRaw = $col($row, ['amount', 'fare', 'toll amount', 'charge']);

            $amount = (float) preg_replace('/[^\d.\-]/', '', (string) $amountRaw);
            try {
                $postedAt = $postedRaw ? Carbon::parse($postedRaw) : null;
            } catch (\Throwable) { $postedAt = null; }

            // Dedupe
            if ($extRef && $postedAt && EzPassTransaction::where('external_ref', $extRef)->where('posted_at', $postedAt)->exists()) {
                $skipped++; continue;
            }

            // Auto-link by plate
            $vehicleId = null;
            if ($plate) {
                $key = strtoupper(preg_replace('/\s+/', '', $plate));
                $vehicleId = $vehiclesByPlate[$key]->id ?? null;
            }

            // Auto-link to reservation active on the toll's day
            $reservationId = null;
            $customerId = null;
            if ($vehicleId && $postedAt) {
                $reservation = Reservation::where('vehicle_id', $vehicleId)
                    ->where('pickup_date', '<=', $postedAt)
                    ->where(function ($q) use ($postedAt) {
                        $q->whereNull('actual_return_date')->orWhere('actual_return_date', '>=', $postedAt);
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
                'tag_number'     => $tagNumber ?: null,
                'plate'          => $plate ?: null,
                'posted_at'      => $postedAt,
                'agency'         => $agency,
                'plaza'          => $plaza,
                'lane'           => $lane,
                'amount'         => $amount,
                'type'           => 'toll',
                'source_file'    => $fileName,
                'external_ref'   => $extRef,
            ]);
            $imported++;
        }

        return back()->with('success',
            "Imported $imported transactions · linked $linked to active rentals · skipped $skipped duplicate/empty rows.");
    }

    private function readRows(string $path, string $ext): array
    {
        if (in_array(strtolower($ext), ['xlsx', 'xls'], true)) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            return $reader->getActiveSheet()->toArray();
        }
        // CSV / TXT
        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            while (($r = fgetcsv($h)) !== false) $rows[] = $r;
            fclose($h);
        }
        return $rows;
    }
}
