<?php

namespace Database\Seeders;

use App\Models\Lender;
use App\Models\LenderProgram;
use Illuminate\Database\Seeder;

class SampleLenderProgramsSeeder extends Seeder
{
    public function run(): void
    {
        $validFrom = now()->startOfMonth()->format('Y-m-d');
        $validUntil = now()->addMonth()->endOfMonth()->format('Y-m-d');

        // Programs based on typical April 2026 manufacturer rates
        $programs = [
            // Honda Financial - Honda models
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'Odyssey', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 60.0, 'mf' => 0.00185, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'Odyssey', 'year' => 2026, 'term' => 36, 'mileage' => 12000, 'residual' => 58.0, 'mf' => 0.00185, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'Odyssey', 'year' => 2026, 'term' => 36, 'mileage' => 15000, 'residual' => 56.0, 'mf' => 0.00185, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'Odyssey', 'year' => 2026, 'term' => 24, 'mileage' => 10000, 'residual' => 67.0, 'mf' => 0.00175, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'Odyssey', 'year' => 2026, 'term' => 39, 'mileage' => 10000, 'residual' => 56.0, 'mf' => 0.00190, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'CR-V', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 62.0, 'mf' => 0.00175, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'CR-V', 'year' => 2026, 'term' => 36, 'mileage' => 12000, 'residual' => 60.0, 'mf' => 0.00175, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'Pilot', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 58.0, 'mf' => 0.00210, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'Accord', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 57.0, 'mf' => 0.00200, 'acq' => 595],
            ['lender' => 'American Honda Finance Corp.', 'make' => 'Honda', 'model' => 'HR-V', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 59.0, 'mf' => 0.00195, 'acq' => 595],

            // Toyota Financial
            ['lender' => 'Toyota Financial Services', 'make' => 'Toyota', 'model' => 'Sienna', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 63.0, 'mf' => 0.00150, 'acq' => 650],
            ['lender' => 'Toyota Financial Services', 'make' => 'Toyota', 'model' => 'Sienna', 'year' => 2026, 'term' => 36, 'mileage' => 12000, 'residual' => 61.0, 'mf' => 0.00150, 'acq' => 650],
            ['lender' => 'Toyota Financial Services', 'make' => 'Toyota', 'model' => 'Camry', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 58.0, 'mf' => 0.00185, 'acq' => 650],
            ['lender' => 'Toyota Financial Services', 'make' => 'Toyota', 'model' => 'Tacoma', 'year' => 2025, 'term' => 36, 'mileage' => 10000, 'residual' => 70.0, 'mf' => 0.00170, 'acq' => 650],
            ['lender' => 'Toyota Financial Services', 'make' => 'Toyota', 'model' => 'RAV4', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 64.0, 'mf' => 0.00165, 'acq' => 650],

            // Hyundai Motor Finance
            ['lender' => 'Hyundai Motor Finance', 'make' => 'Hyundai', 'model' => 'Palisade', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 58.0, 'mf' => 0.00220, 'acq' => 650],
            ['lender' => 'Hyundai Motor Finance', 'make' => 'Hyundai', 'model' => 'Palisade', 'year' => 2026, 'term' => 36, 'mileage' => 12000, 'residual' => 56.0, 'mf' => 0.00220, 'acq' => 650],
            ['lender' => 'Hyundai Motor Finance', 'make' => 'Hyundai', 'model' => 'Tucson', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 60.0, 'mf' => 0.00195, 'acq' => 650],
            ['lender' => 'Hyundai Motor Finance', 'make' => 'Hyundai', 'model' => 'Santa Fe', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 58.0, 'mf' => 0.00210, 'acq' => 650],
            ['lender' => 'Hyundai Motor Finance', 'make' => 'Hyundai', 'model' => 'Elantra', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 56.0, 'mf' => 0.00225, 'acq' => 650],

            // Kia Motors Finance
            ['lender' => 'Kia Motors Finance', 'make' => 'Kia', 'model' => 'Telluride', 'year' => 2027, 'term' => 36, 'mileage' => 10000, 'residual' => 62.0, 'mf' => 0.00180, 'acq' => 650],
            ['lender' => 'Kia Motors Finance', 'make' => 'Kia', 'model' => 'Telluride', 'year' => 2027, 'term' => 36, 'mileage' => 12000, 'residual' => 60.0, 'mf' => 0.00180, 'acq' => 650],
            ['lender' => 'Kia Motors Finance', 'make' => 'Kia', 'model' => 'Carnival', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 57.0, 'mf' => 0.00210, 'acq' => 650],
            ['lender' => 'Kia Motors Finance', 'make' => 'Kia', 'model' => 'Sportage', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 59.0, 'mf' => 0.00200, 'acq' => 650],
            ['lender' => 'Kia Motors Finance', 'make' => 'Kia', 'model' => 'Sorento', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 58.0, 'mf' => 0.00195, 'acq' => 650],
            ['lender' => 'Kia Motors Finance', 'make' => 'Kia', 'model' => 'EV9', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 50.0, 'mf' => 0.00250, 'acq' => 650],

            // Nissan
            ['lender' => 'Nissan Motor Acceptance', 'make' => 'Nissan', 'model' => 'Pathfinder', 'year' => 2025, 'term' => 36, 'mileage' => 10000, 'residual' => 56.0, 'mf' => 0.00225, 'acq' => 650],
            ['lender' => 'Nissan Motor Acceptance', 'make' => 'Nissan', 'model' => 'Murano', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 55.0, 'mf' => 0.00230, 'acq' => 650],

            // Lincoln
            ['lender' => 'Lincoln Automotive Financial', 'make' => 'Lincoln', 'model' => 'Aviator', 'year' => 2025, 'term' => 36, 'mileage' => 10000, 'residual' => 55.0, 'mf' => 0.00250, 'acq' => 745],
            ['lender' => 'Lincoln Automotive Financial', 'make' => 'Lincoln', 'model' => 'Corsair', 'year' => 2025, 'term' => 36, 'mileage' => 10000, 'residual' => 54.0, 'mf' => 0.00245, 'acq' => 745],

            // Chevrolet (GM Financial)
            ['lender' => 'GM Financial', 'make' => 'Chevrolet', 'model' => 'Trailblazer', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 56.0, 'mf' => 0.00210, 'acq' => 650],
            ['lender' => 'GM Financial', 'make' => 'Chevrolet', 'model' => 'Equinox', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 55.0, 'mf' => 0.00210, 'acq' => 650],
            ['lender' => 'GM Financial', 'make' => 'Chevrolet', 'model' => 'Traverse', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 54.0, 'mf' => 0.00220, 'acq' => 650],
            ['lender' => 'GM Financial', 'make' => 'Chevrolet', 'model' => 'Suburban', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 60.0, 'mf' => 0.00195, 'acq' => 650],
            ['lender' => 'GM Financial', 'make' => 'GMC', 'model' => 'Yukon XL', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 58.0, 'mf' => 0.00200, 'acq' => 650],

            // Infiniti
            ['lender' => 'Infiniti Financial Services', 'make' => 'Infiniti', 'model' => 'QX60', 'year' => 2026, 'term' => 36, 'mileage' => 10000, 'residual' => 53.0, 'mf' => 0.00255, 'acq' => 700],

            // Finance programs (APR-based, not lease)
            ['lender' => 'ALLY Financial', 'make' => null, 'model' => null, 'year' => null, 'term' => 60, 'mileage' => null, 'apr' => 6.49, 'acq' => 0, 'type' => 'finance'],
            ['lender' => 'ALLY Financial', 'make' => null, 'model' => null, 'year' => null, 'term' => 72, 'mileage' => null, 'apr' => 7.24, 'acq' => 0, 'type' => 'finance'],
            ['lender' => 'Capital One Auto Finance', 'make' => null, 'model' => null, 'year' => null, 'term' => 60, 'mileage' => null, 'apr' => 6.99, 'acq' => 0, 'type' => 'finance'],
            ['lender' => 'Capital One Auto Finance', 'make' => null, 'model' => null, 'year' => null, 'term' => 72, 'mileage' => null, 'apr' => 7.49, 'acq' => 0, 'type' => 'finance'],
            ['lender' => 'Hudson Valley Federal Credit Union', 'make' => null, 'model' => null, 'year' => null, 'term' => 60, 'mileage' => null, 'apr' => 5.99, 'acq' => 0, 'type' => 'finance'],
            ['lender' => 'Hudson Valley Federal Credit Union', 'make' => null, 'model' => null, 'year' => null, 'term' => 72, 'mileage' => null, 'apr' => 6.49, 'acq' => 0, 'type' => 'finance'],
            ['lender' => 'Navy Federal Credit Union', 'make' => null, 'model' => null, 'year' => null, 'term' => 60, 'mileage' => null, 'apr' => 5.49, 'acq' => 0, 'type' => 'finance'],
        ];

        $imported = 0;
        $skipped = 0;
        foreach ($programs as $row) {
            $lender = Lender::where('name', $row['lender'])->first();
            if (!$lender) { $skipped++; continue; }

            LenderProgram::create([
                'lender_id' => $lender->id,
                'program_type' => $row['type'] ?? 'lease',
                'make' => $row['make'],
                'model' => $row['model'],
                'year' => $row['year'],
                'term' => $row['term'],
                'annual_mileage' => $row['mileage'],
                'residual_pct' => $row['residual'] ?? null,
                'money_factor' => $row['mf'] ?? null,
                'apr' => $row['apr'] ?? null,
                'acquisition_fee' => $row['acq'] ?? 0,
                'min_credit_score' => 680,
                'credit_tier' => 'tier_1',
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'is_active' => true,
                'source' => 'manual',
                'notes' => 'Sample April 2026 program',
            ]);
            $imported++;
        }

        $this->command->info("Imported {$imported} lender programs ({$skipped} skipped — lender not found).");
    }
}
