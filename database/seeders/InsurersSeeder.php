<?php

namespace Database\Seeders;

use App\Models\Insurer;
use Illuminate\Database\Seeder;

class InsurersSeeder extends Seeder
{
    public function run(): void
    {
        $insurers = [
            // National
            'GEICO', 'Progressive', 'State Farm', 'Allstate', 'USAA',
            'Liberty Mutual', 'Farmers Insurance', 'Nationwide', 'American Family',
            'Travelers', 'Erie Insurance', 'Auto-Owners Insurance', 'The Hartford',
            'MetLife Auto', 'Esurance', 'Mercury Insurance', 'Safeco Insurance',
            'Plymouth Rock', 'Kemper', 'Direct Auto Insurance',
            // Regional / NY-focused
            'NJM Insurance', 'NYCM Insurance', 'Plymouth Rock Assurance',
            'MAPFRE', 'Hanover Insurance', 'National General',
            'Bristol West', 'Foremost Insurance', 'Dairyland',
            'Infinity Insurance', 'Titan Insurance',
            // Specialty
            'Hagerty', 'Grundy', 'American Modern',
            // Commercial
            'Commercial Auto Insurance', 'Progressive Commercial',
        ];

        $imported = 0;
        foreach ($insurers as $name) {
            $insurer = Insurer::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
            if ($insurer->wasRecentlyCreated) $imported++;
        }

        $this->command->info("Imported {$imported} insurers.");
    }
}
