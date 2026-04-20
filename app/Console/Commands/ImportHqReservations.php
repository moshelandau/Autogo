<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ImportHqReservations extends Command
{
    protected $signature = 'import:hq-reservations {file}';
    protected $description = 'Import reservations from HQ Rentals XLSX export';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Reading {$file}...");

        $reader = new Xlsx();
        $spreadsheet = $reader->load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $headers = array_shift($rows);
        $this->info("Found " . count($rows) . " reservations to import.");

        // Pre-load locations and vehicles for matching
        $locations = Location::pluck('id', 'name');
        $vehicles = Vehicle::all()->keyBy('license_plate');

        $imported = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $data = array_combine($headers, $row);

            $resNumber = trim($data['#'] ?? '');
            if (empty($resNumber)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Skip if already imported
            if (Reservation::where('reservation_number', $resNumber)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Match customer by name
            $customerName = trim($data['Customer'] ?? '');
            $customer = $this->findOrCreateCustomer($customerName);

            if (!$customer) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Match vehicle by plate from "Make Model - PLATE" format
            $vehicleId = null;
            $vehicleStr = trim($data['Vehicle'] ?? '');
            if ($vehicleStr && $vehicleStr !== '-') {
                $parts = explode(' - ', $vehicleStr);
                $plate = trim(end($parts));
                $vehicle = $vehicles->get($plate);
                $vehicleId = $vehicle?->id;
            }

            // Parse location
            $locationName = trim($data['Pickup Location'] ?? '');
            $locationId = $locations->get($locationName);

            // Parse dates
            $pickupDate = $this->parseDate($data['Pickup Date'] ?? '');
            $returnDate = $this->parseDate($data['Return Date'] ?? '');

            // Parse amounts (remove $ and commas)
            $totalPrice = $this->parseAmount($data['Total Price'] ?? '0');
            $totalPaid = $this->parseAmount($data['Total Paid'] ?? '0');
            $totalRefunded = $this->parseAmount($data['Total Refunded'] ?? '0');
            $outstandingBalance = $this->parseAmount($data['Outstanding Balance'] ?? '0');
            $dailyRate = min($this->parseAmount($data['Daily Rate'] ?? '0'), 999999);
            $totalDays = (int) ($data['Total Days'] ?? 1);

            // Map status
            $status = match (strtolower(trim($data['Status'] ?? ''))) {
                'rental' => 'rental',
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                'no show' => 'no_show',
                default => 'open',
            };

            Reservation::create([
                'reservation_number' => $resNumber,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicleId,
                'vehicle_class' => $data['Vehicle Class'] ?? null,
                'pickup_location_id' => $locationId,
                'return_location_id' => $locationId,
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'daily_rate' => $dailyRate,
                'total_days' => $totalDays,
                'subtotal' => $dailyRate * $totalDays,
                'total_price' => $totalPrice,
                'total_paid' => $totalPaid,
                'total_refunded' => $totalRefunded,
                'outstanding_balance' => $outstandingBalance,
                'status' => $status,
                'notes' => $data['Notes'] ?? null,
            ]);

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Imported: {$imported} reservations, Skipped: {$skipped}");

        return 0;
    }

    private function findOrCreateCustomer(string $name): ?Customer
    {
        if (empty($name)) return null;

        $nameParts = explode(' ', $name);
        if (count($nameParts) >= 2) {
            $lastName = array_pop($nameParts);
            $firstName = implode(' ', $nameParts);
        } else {
            $firstName = $name;
            $lastName = '';
        }

        // Try exact match first
        $customer = Customer::where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->first();

        if ($customer) return $customer;

        // Try case-insensitive
        $customer = Customer::whereRaw('LOWER(first_name) = ? AND LOWER(last_name) = ?', [
            strtolower($firstName), strtolower($lastName)
        ])->first();

        if ($customer) return $customer;

        // Create minimal record
        return Customer::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
        ]);
    }

    private function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) return null;
        try {
            return \Carbon\Carbon::parse($dateStr)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseAmount(?string $amount): float
    {
        if (empty($amount)) return 0;
        return (float) str_replace(['$', ','], '', $amount);
    }
}
