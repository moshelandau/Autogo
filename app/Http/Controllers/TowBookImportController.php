<?php

namespace App\Http\Controllers;

use App\Models\TowDriver;
use App\Models\TowJob;
use App\Models\TowTruck;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TowBookImportController extends Controller
{
    /**
     * Bulk import historical TowBook calls.
     * Body: { jobs: [ { call_number, service_provider, driver, truck, date, type, po_number, membership, dispatch_number, money_received, po_amount }, ... ] }
     */
    public function importBatch(Request $request)
    {
        $jobs = $request->input('jobs', []);
        $imported = 0;
        $skipped = 0;
        $trucksMap  = [];
        $driversMap = [];

        foreach ($jobs as $row) {
            $callNo = trim((string)($row['call_number'] ?? ''));
            if ($callNo === '') { $skipped++; continue; }

            $jobNumber = 'TB-' . str_pad($callNo, 5, '0', STR_PAD_LEFT);
            if (TowJob::where('job_number', $jobNumber)->exists()) { $skipped++; continue; }

            // Find or create truck/driver
            $truck = null;
            if (!empty(trim($row['truck'] ?? ''))) {
                $name = trim($row['truck']);
                $truck = $trucksMap[$name] ??= TowTruck::firstOrCreate(['name' => $name], ['is_active' => true]);
            }
            $driver = null;
            if (!empty(trim($row['driver'] ?? ''))) {
                $name = trim($row['driver']);
                $driver = $driversMap[$name] ??= TowDriver::firstOrCreate(['name' => $name], ['is_active' => true]);
            }

            $requestedAt = null;
            try {
                $requestedAt = Carbon::createFromFormat('n/j/Y g:i:s A', trim($row['date'] ?? ''));
            } catch (\Throwable) {
                try { $requestedAt = Carbon::parse(trim($row['date'] ?? '')); } catch (\Throwable) {}
            }

            $type = strtolower(trim($row['type'] ?? ''));
            $reason = match (true) {
                str_contains($type, 'tow')   => 'accident',
                str_contains($type, 'tire')  => 'breakdown',
                str_contains($type, 'fuel')  => 'breakdown',
                str_contains($type, 'lock')  => 'breakdown',
                str_contains($type, 'jump')  => 'breakdown',
                default                       => 'other',
            };

            $billed = (float) preg_replace('/[^0-9.]/', '', $row['po_amount'] ?? '0');
            $paid   = (float) preg_replace('/[^0-9.]/', '', $row['money_received'] ?? '0');

            TowJob::create([
                'job_number'        => $jobNumber,
                'tow_truck_id'      => $truck?->id,
                'tow_driver_id'     => $driver?->id,
                'insurance_company' => trim($row['service_provider'] ?? '') ?: null,
                'reference_number'  => trim($row['po_number'] ?? '') ?: trim($row['dispatch_number'] ?? '') ?: null,
                'pickup_address'    => '— from TowBook (no address recorded in summary) —',
                'dropoff_address'   => '— from TowBook (no address recorded in summary) —',
                'reason'            => $reason,
                'priority'          => 'normal',
                'status'            => 'completed', // historical
                'billed_amount'     => $billed ?: null,
                'paid_amount'       => $paid,
                'requested_at'      => $requestedAt,
                'completed_at'      => $requestedAt,
                'notes'             => "Imported from TowBook (call #{$callNo}, type: {$type}, membership: " . ($row['membership'] ?? '') . ")",
                'created_by'        => auth()->id(),
            ]);
            $imported++;
        }

        return response()->json([
            'ok'       => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'trucks'   => count($trucksMap),
            'drivers'  => count($driversMap),
        ]);
    }
}
