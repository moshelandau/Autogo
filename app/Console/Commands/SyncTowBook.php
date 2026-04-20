<?php

namespace App\Console\Commands;

use App\Models\TowDriver;
use App\Models\TowJob;
use App\Models\TowTruck;
use App\Services\TowBookService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncTowBook extends Command
{
    protected $signature = 'sync:towbook {--from=} {--to=} {--full}';
    protected $description = 'Pull recent (or all) tow calls from TowBook API into AutoGo.';

    public function handle(TowBookService $tb): int
    {
        if (!$tb->isConfigured()) {
            $this->error('TowBook not configured — add TOWBOOK_CLIENT_ID and TOWBOOK_CLIENT_SECRET (or set via Settings).');
            return 1;
        }

        $from = $this->option('from') ?: ($this->option('full') ? '2020-01-01' : now()->subDays(7)->toDateString());
        $to   = $this->option('to')   ?: now()->toDateString();
        $this->info("Syncing TowBook calls {$from} → {$to}");

        $page = 1; $totalPulled = 0; $imported = 0; $updated = 0;
        $trucksMap = []; $driversMap = [];

        while (true) {
            $resp = $tb->calls($from, $to, $page, 100);
            $calls = $resp['data'] ?? $resp ?? [];
            if (empty($calls)) break;

            foreach ($calls as $c) {
                $callId = $c['id'] ?? $c['call_id'] ?? null;
                if (!$callId) continue;

                $jobNumber = 'TB-' . str_pad((string)$callId, 5, '0', STR_PAD_LEFT);
                $existing = TowJob::where('job_number', $jobNumber)->first();

                $truckId = null;
                if ($truckName = trim((string)($c['truck']['name'] ?? $c['truck_name'] ?? ''))) {
                    $truckId = ($trucksMap[$truckName] ??= TowTruck::firstOrCreate(['name'=>$truckName], ['is_active'=>true]))->id;
                }
                $driverId = null;
                if ($driverName = trim((string)($c['driver']['name'] ?? $c['driver_name'] ?? ''))) {
                    $driverId = ($driversMap[$driverName] ??= TowDriver::firstOrCreate(['name'=>$driverName], ['is_active'=>true]))->id;
                }

                $statusMap = [
                    'scheduled'  => 'pending',     'waiting' => 'pending',
                    'dispatched' => 'dispatched',  'enroute' => 'en_route',
                    'on_scene'   => 'on_scene',    'towing'  => 'in_transit',
                    'destination_arrival' => 'in_transit',
                    'completed'  => 'completed',   'cancelled' => 'cancelled',
                ];
                $status = $statusMap[strtolower($c['status'] ?? '')] ?? 'completed';

                $data = [
                    'job_number'        => $jobNumber,
                    'tow_truck_id'      => $truckId,
                    'tow_driver_id'     => $driverId,
                    'caller_name'       => $c['caller']['name'] ?? null,
                    'caller_phone'      => $c['caller']['phone'] ?? null,
                    'insurance_company' => $c['account']['name'] ?? $c['service_provider'] ?? null,
                    'reference_number'  => $c['po_number'] ?? $c['dispatch_number'] ?? null,
                    'vehicle_year'      => $c['vehicle']['year'] ?? null,
                    'vehicle_make'      => $c['vehicle']['make'] ?? null,
                    'vehicle_model'     => $c['vehicle']['model'] ?? null,
                    'vehicle_color'     => $c['vehicle']['color'] ?? null,
                    'vehicle_plate'     => $c['vehicle']['plate'] ?? null,
                    'vehicle_vin'       => $c['vehicle']['vin'] ?? null,
                    'pickup_address'    => $c['pickup']['address'] ?? '— —',
                    'pickup_city'       => $c['pickup']['city'] ?? null,
                    'pickup_state'      => $c['pickup']['state'] ?? null,
                    'pickup_zip'        => $c['pickup']['zip'] ?? null,
                    'pickup_lat'        => $c['pickup']['lat'] ?? null,
                    'pickup_lng'        => $c['pickup']['lng'] ?? null,
                    'dropoff_address'   => $c['dropoff']['address'] ?? '— —',
                    'dropoff_city'      => $c['dropoff']['city'] ?? null,
                    'dropoff_state'     => $c['dropoff']['state'] ?? null,
                    'dropoff_zip'       => $c['dropoff']['zip'] ?? null,
                    'status'            => $status,
                    'priority'          => $c['priority'] ?? 'normal',
                    'reason'            => 'other',
                    'quoted_amount'     => $c['quoted_amount']  ?? null,
                    'billed_amount'     => $c['billed_amount']  ?? null,
                    'paid_amount'       => $c['paid_amount']    ?? 0,
                    'requested_at'      => isset($c['created_at']) ? Carbon::parse($c['created_at']) : null,
                    'dispatched_at'     => isset($c['dispatched_at']) ? Carbon::parse($c['dispatched_at']) : null,
                    'on_scene_at'       => isset($c['on_scene_at'])   ? Carbon::parse($c['on_scene_at'])   : null,
                    'completed_at'      => isset($c['completed_at'])  ? Carbon::parse($c['completed_at'])  : null,
                    'notes'             => $c['notes'] ?? null,
                ];

                if ($existing) { $existing->update($data); $updated++; }
                else            { TowJob::create($data); $imported++; }
                $totalPulled++;
            }

            // Pagination — break if no next page
            if (count($calls) < 100) break;
            $page++;
        }

        $this->info("Pulled: {$totalPulled} · Imported: {$imported} · Updated: {$updated} · Trucks: ".count($trucksMap)." · Drivers: ".count($driversMap));
        return 0;
    }
}
