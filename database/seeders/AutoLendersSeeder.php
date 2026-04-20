<?php

namespace Database\Seeders;

use App\Models\Lender;
use Illuminate\Database\Seeder;

class AutoLendersSeeder extends Seeder
{
    public function run(): void
    {
        $lenders = [
            // OEM Captives (manufacturer financing)
            'American Honda Finance Corp.',
            'Toyota Financial Services',
            'Ford Motor Credit',
            'GM Financial',
            'Hyundai Motor Finance',
            'Kia Motors Finance',
            'Nissan Motor Acceptance',
            'Mazda Capital Services',
            'Subaru Motors Finance',
            'Volkswagen Credit',
            'BMW Financial Services',
            'Mercedes-Benz Financial Services',
            'Audi Financial Services',
            'Volvo Car Financial Services',
            'Infiniti Financial Services',
            'Acura Financial Services',
            'Lexus Financial Services',
            'Aston Martin Financial Services',
            'Bentley Financial Services',
            'Porsche Financial Services',
            'Jaguar Financial Group',
            'Land Rover Financial Group',
            'Mitsubishi Motors Credit',
            'Chase Auto McLaren',
            'Genesis Finance',
            'Lincoln Automotive Financial',
            'Cadillac Financial',
            'Stellantis Financial Services',
            'Chrysler Capital',

            // Major Banks
            'ALLY Financial',
            'Capital One Auto Finance',
            'Bank of America',
            'Wells Fargo Auto',
            'Chase Auto Finance',
            'Citizens One Auto',
            'TD Auto Finance',
            'PNC Bank',
            'Fifth Third Bank',
            'Santander Consumer USA',
            'US Bank Auto',
            'Huntington Auto Finance',
            'Truist Bank',
            'M&T Bank',
            'Comerica Bank',
            'Commerce Bank',
            'Westlake Financial',
            'Exeter Finance',
            'GLS / Global Lending Services',

            // Credit Unions
            'Navy Federal Credit Union',
            'Pentagon Federal Credit Union',
            'PenFed Credit Union',
            'Hudson Valley Federal Credit Union',
            'Affinity Federal Credit Union',
            'American Eagle Financial C.U.',
            'Advancial F.C.U.',
            'Advia Credit Union',
            'Alltru F.C.U.',
            'American Broadcast Employees F.C.U.',
            'Atomic C.U.',
            'Canyon View Credit Union',
            'Catholic & Community C.U.',
            'Citadel C.U.',
            'Columbia C.U.',
            'Coosa Valley C.U.',
            'CUDC Bellco C.U.',
            'CUDC Blue F.C.U.',
            'CUDC Climb C.U.',
            'CUDC Horizons North C.U.',
            'CUDC ZING C.U.',
            'Credit Union Acceptance Group',
            'DCU - Digital Federal Credit Union',
            'Suncoast Credit Union',
            'BECU - Boeing Employees Credit Union',
            'SchoolsFirst Federal Credit Union',
            'America First Credit Union',
            'Alliant Credit Union',
            'Mountain America Credit Union',
            'Randolph-Brooks Federal Credit Union',
            'Golden 1 Credit Union',
            'Star One Credit Union',
            'Teachers Credit Union',
            'State Employees Credit Union',
            'VyStar Credit Union',
            'Patelco Credit Union',
            'Logix Federal Credit Union',
            'First Tech Federal Credit Union',
            'Empower Federal Credit Union',
            'ESL Federal Credit Union',
            'ELGA Credit Union',
            'GreenState Credit Union',
            'Heritage Family Credit Union',
            'NJM Bank',
            'NorthEast Credit Union',
            'NYMCU New York Municipal Credit Union',
            'OneAZ Credit Union',
            'Orange County Credit Union',
            'PSECU Pennsylvania State Employees',
            'Quorum Federal Credit Union',
            'Redstone Federal Credit Union',
            'San Diego County Credit Union',
            'Self-Help Credit Union',
            'Tower Federal Credit Union',
            'TruWest Credit Union',
            'United Federal Credit Union',
            'USAA Federal Savings Bank',
            'Visions Federal Credit Union',
            'Wright-Patt Credit Union',
            'XCEL Federal Credit Union',
            'Yolo Federal Credit Union',
            'Yonkers Postal Employees Credit Union',
            'YourCause Federal Credit Union',
            'Zia Credit Union',

            // Subprime / Specialty
            'CarFinance.com',
            'Credit Acceptance',
            'DriveTime',
            'Prestige Financial Services',
            'United Auto Credit',
            'AmeriCredit',
            'Skopos Financial',
            'Foursight Capital',
            'CIG Financial',

            // Lease Specialists
            'Origence Lending Services',
            'CULA - Credit Union Leasing of America',
            'Lendmark Financial',
        ];

        $imported = 0;
        $skipped = 0;
        foreach ($lenders as $i => $name) {
            $lender = Lender::firstOrCreate(
                ['name' => $name],
                ['is_active' => true, 'sort_order' => $i]
            );
            if ($lender->wasRecentlyCreated) $imported++;
            else $skipped++;
        }

        $this->command->info("Imported {$imported} new lenders, skipped {$skipped} existing.");
    }
}
