<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ImportHqCustomers extends Command
{
    protected $signature = 'import:hq-customers {file}';
    protected $description = 'Import customers from HQ Rentals XLSX export';

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
        $this->info("Found " . count($rows) . " customers to import.");

        $imported = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $data = array_combine($headers, $row);

            $label = trim($data['Label'] ?? '');
            if (empty($label)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Parse name — split on last space for first/last
            $nameParts = explode(' ', $label);
            if (count($nameParts) >= 2) {
                $lastName = array_pop($nameParts);
                $firstName = implode(' ', $nameParts);
            } else {
                $firstName = $label;
                $lastName = '';
            }

            // Parse phone — clean up
            $phone = trim($data['Phone Number'] ?? '');

            // Parse DL expiration
            $dlExpiration = null;
            if (!empty($data['Expiration Date'])) {
                try {
                    $dlExpiration = \Carbon\Carbon::parse($data['Expiration Date'])->toDateString();
                } catch (\Exception $e) {
                    // skip invalid dates
                }
            }

            Customer::firstOrCreate(
                ['first_name' => $firstName, 'last_name' => $lastName, 'phone' => $phone],
                [
                    'email' => $data['Email Address'] ?? null,
                    'address' => $data['Street'] ?? null,
                    'address_2' => $data['Street 2'] ?? null,
                    'city' => $data['City'] ?? null,
                    'state' => $data['State'] ?? null,
                    'zip' => $data['Zip'] ?? null,
                    'country' => $data['Country'] === 'United States' ? 'US' : ($data['Country'] ?? 'US'),
                    'drivers_license_number' => $data['DL Number'] ?? null,
                    'dl_expiration' => $dlExpiration,
                    'is_active' => true,
                    'can_receive_sms' => true,
                ]
            );

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Imported: {$imported} customers, Skipped: {$skipped}");

        return 0;
    }
}
