<?php

namespace Database\Seeders;

use App\Models\InsuranceBroker;
use Illuminate\Database\Seeder;

/**
 * Real broker list — sourced from the xDeskPro Insurers modal
 * (15 brokers, captured 2026-04-30). firstOrCreate is idempotent so
 * re-running this seeder is safe; existing rows are left alone.
 */
class InsuranceBrokersSeeder extends Seeder
{
    public function run(): void
    {
        $brokers = [
            ['name' => 'A Thru Z Insurance',     'first_name' => 'Mindy',       'last_name' => 'Az',                   'email' => 'Mindy@athruzins.com',          'phone' => '(845) 783-1201'],
            ['name' => 'JNR Insurance Agency',   'first_name' => null,          'last_name' => null,                   'email' => null,                           'phone' => '(845) 555-5555'],
            ['name' => 'Glanzer',                'first_name' => 'Glanzer',     'last_name' => 'Insurance Agency LLC', 'email' => 'joel@glanzerinsurance.com',    'phone' => '(845) 783-7000'],
            ['name' => 'OCI Brokerage',          'first_name' => 'OCI',         'last_name' => 'Brokerage Ins',        'email' => 'ociinsurance@gmail.com',       'phone' => '(845) 774-2260'],
            ['name' => 'Travelers',              'first_name' => 'Mindy',       'last_name' => 'A',                    'email' => 'Mindy@athruzins.com',          'phone' => '(718) 599-4747'],
            ['name' => 'Nationwide',             'first_name' => 'Joseph',      'last_name' => 'Fisch',                'email' => null,                           'phone' => '(121) 247-0195'],
            ['name' => 'Kemper',                 'first_name' => 'Yaakov',      'last_name' => 'Lichter',              'email' => null,                           'phone' => '(845) 262-2464'],
            ['name' => 'Silberstein Agency Inc', 'first_name' => 'Silberstein', 'last_name' => 'Agency Inc',           'email' => null,                           'phone' => '(845) 782-2500'],
            ['name' => 'GEICO',                  'first_name' => 'GEICO',       'last_name' => 'Online',               'email' => null,                           'phone' => '(800) 207-7847'],
            ['name' => 'Progressive',            'first_name' => 'Progressive', 'last_name' => 'Direct',               'email' => null,                           'phone' => '(888) 671-4405'],
            ['name' => 'Choice Usa Agcy Inc',    'first_name' => 'Choice Usa',  'last_name' => 'Agcy Inc',             'email' => null,                           'phone' => '(718) 854-1010'],
            ['name' => 'Prime Point',            'first_name' => 'Prime Point', 'last_name' => 'Insurance',            'email' => null,                           'phone' => '(845) 782-3325'],
            ['name' => 'Hirschfeld & Associates','first_name' => 'Hirschfeld',  'last_name' => 'A',                    'email' => 'info@hirschfeldandassociates.com', 'phone' => '(718) 522-6555'],
            ['name' => 'Blueshine',              'first_name' => 'Y.',          'last_name' => 'Gandl',                'email' => 'yg@blueshineinsurance.com',    'phone' => '(845) 751-9299 x103'],
            ['name' => 'HF Insurance Agency',    'first_name' => 'Hershy',      'last_name' => 'Fisher',               'email' => null,                           'phone' => '(845) 210-5700'],
        ];

        $imported = 0;
        foreach ($brokers as $b) {
            $row = InsuranceBroker::firstOrCreate(
                ['name' => $b['name']],
                array_merge($b, ['is_active' => true])
            );
            if ($row->wasRecentlyCreated) $imported++;
        }

        $this->command->info("Imported {$imported} insurance brokers from xDeskPro list (skipped " . (count($brokers) - $imported) . " that already existed).");
    }
}
