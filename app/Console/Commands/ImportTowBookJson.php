<?php

namespace App\Console\Commands;

use App\Models\TowDriver;
use App\Models\TowJob;
use App\Models\TowTruck;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportTowBookJson extends Command
{
    protected $signature = 'import:towbook {file}';
    protected $description = 'Import a TowBook Motor Club Report JSON dump (array of arrays).';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!file_exists($file)) { $this->error("File not found: $file"); return 1; }

        $payload = json_decode(file_get_contents($file), true);
        if (!is_array($payload) || empty($payload)) { $this->error("Invalid JSON or empty array"); return 1; }

        $rows = isset($payload[0]) && is_array($payload[0]) ? $payload : [];
        $this->info('Rows: '.count($rows));

        $imported = 0; $skipped = 0;
        $trucksMap = []; $driversMap = [];

        foreach ($rows as $r) {
            // Each row: [Call#, Service Provider, Driver, Truck, Date, Type, PO#, Membership#, Dispatch#, Money Received, PO Amount Total]
            [$call, $provider, $driver, $truck, $date, $type, $po, $membership, $dispatch, $moneyR, $poAmt] = array_pad($r, 11, '');

            $callNo = trim((string)$call);
            if ($callNo === '' || !is_numeric($callNo)) { $skipped++; continue; }

            $jobNumber = 'TB-'.str_pad($callNo, 5, '0', STR_PAD_LEFT);
            if (TowJob::where('job_number', $jobNumber)->exists()) { $skipped++; continue; }

            $truckObj = null;
            $tName = trim((string)$truck);
            if ($tName !== '') {
                $truckObj = $trucksMap[$tName] ??= TowTruck::firstOrCreate(['name'=>$tName], ['is_active'=>true]);
            }
            $driverObj = null;
            $dName = trim((string)$driver);
            if ($dName !== '') {
                $driverObj = $driversMap[$dName] ??= TowDriver::firstOrCreate(['name'=>$dName], ['is_active'=>true]);
            }

            $reqAt = null;
            try { $reqAt = Carbon::createFromFormat('n/j/Y g:i:s A', trim((string)$date)); }
            catch (\Throwable) { try { $reqAt = Carbon::parse(trim((string)$date)); } catch (\Throwable) {} }

            $reason = match (true) {
                str_contains(strtolower($type), 'tow')   => 'accident',
                str_contains(strtolower($type), 'tire')  => 'breakdown',
                str_contains(strtolower($type), 'fuel')  => 'breakdown',
                str_contains(strtolower($type), 'lock')  => 'breakdown',
                str_contains(strtolower($type), 'jump')  => 'breakdown',
                default                                   => 'other',
            };

            $billed = (float) preg_replace('/[^0-9.]/', '', (string)$poAmt);
            $paid   = (float) preg_replace('/[^0-9.]/', '', (string)$moneyR);

            TowJob::create([
                'job_number'        => $jobNumber,
                'tow_truck_id'      => $truckObj?->id,
                'tow_driver_id'     => $driverObj?->id,
                'insurance_company' => trim((string)$provider) ?: null,
                'reference_number'  => trim((string)$po) ?: trim((string)$dispatch) ?: null,
                'pickup_address'    => '— from TowBook (no address in summary export) —',
                'dropoff_address'   => '— from TowBook (no address in summary export) —',
                'reason'            => $reason,
                'priority'          => 'normal',
                'status'            => 'completed',
                'billed_amount'     => $billed ?: null,
                'paid_amount'       => $paid,
                'requested_at'      => $reqAt,
                'completed_at'      => $reqAt,
                'notes'             => "Imported from TowBook · Call #{$callNo} · Type: {$type} · Membership: ".trim((string)$membership),
            ]);
            $imported++;
        }

        $this->info("Imported: {$imported} · Skipped/Dupe: {$skipped}");
        $this->info('Trucks created: '.count($trucksMap).' · Drivers: '.count($driversMap));
        return 0;
    }
}
