<?php

namespace Database\Seeders;

use App\Models\InsuranceBroker;
use Illuminate\Database\Seeder;

/**
 * NOTE: this seeder predates the broker/carrier rename. The names below
 * are insurance CARRIERS (GEICO, Progressive, etc.) — strictly speaking
 * those should live in `deals.insurance_carrier` text, not in the
 * brokers table. We keep the seeder for the legacy import path; clean
 * up by deactivating any wrongly-typed rows from the Brokers index.
 */
class InsuranceBrokersSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            // (legacy — carrier names; see class docblock)
            'GEICO', 'Progressive', 'State Farm', 'Allstate', 'USAA',
            'Liberty Mutual', 'Farmers Insurance', 'Nationwide', 'American Family',
            'Travelers', 'Erie Insurance', 'Auto-Owners Insurance', 'The Hartford',
            'MetLife Auto', 'Esurance', 'Mercury Insurance', 'Safeco Insurance',
            'Plymouth Rock', 'Kemper', 'Direct Auto Insurance',
            'NJM Insurance', 'NYCM Insurance', 'Plymouth Rock Assurance',
            'MAPFRE', 'Hanover Insurance', 'National General',
            'Bristol West', 'Foremost Insurance', 'Dairyland',
            'Infinity Insurance', 'Titan Insurance',
            'Hagerty', 'Grundy', 'American Modern',
            'Commercial Auto Insurance', 'Progressive Commercial',
        ];

        $imported = 0;
        foreach ($names as $name) {
            $row = InsuranceBroker::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
            if ($row->wasRecentlyCreated) $imported++;
        }

        $this->command->info("Imported {$imported} broker rows (legacy seeder).");
    }
}
