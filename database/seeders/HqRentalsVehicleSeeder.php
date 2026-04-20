<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\Location;
use Illuminate\Database\Seeder;

class HqRentalsVehicleSeeder extends Seeder
{
    /**
     * Import all 41 vehicles from HQ Rentals.
     */
    public function run(): void
    {
        $monroe = Location::firstOrCreate(['name' => 'Monroe']);
        $monsey = Location::firstOrCreate(['name' => 'Monsey']);

        $locations = [
            'Monroe' => $monroe->id,
            'Monsey' => $monsey->id,
        ];

        // VIN position 10 year decode map
        $vinYearMap = [
            'A' => 2010, 'B' => 2011, 'C' => 2012, 'D' => 2013, 'E' => 2014,
            'F' => 2015, 'G' => 2016, 'H' => 2017, 'J' => 2018, 'K' => 2019,
            'L' => 2020, 'M' => 2021, 'N' => 2022, 'P' => 2023, 'R' => 2024,
            'S' => 2025, 'T' => 2026,
        ];

        // Rate by class: car=$65, suv=$65, minivan=$95
        $rateMap = [
            'car'     => 65.00,
            'suv'     => 65.00,
            'minivan' => 95.00,
        ];

        // Fuel level mapping from x/8 format
        $fuelMap = [
            '8/8' => 'full',
            '7/8' => 'full',
            '6/8' => '3/4',
            '5/8' => '3/4',
            '4/8' => '1/2',
            '3/8' => '1/4',
            '2/8' => '1/4',
            '1/8' => 'empty',
        ];

        // All 41 vehicles: [hq_id, vin, plate, status, _, make, model, class, location, odometer, fuel]
        $vehicles = [
            [86, '5XYRG4LC2PG212774', 'LUP3894', 'rented', '', 'Kia', 'Sorento', 'suv', 'Monroe', 27645, '8/8'],
            [87, '1HGCY1F38RA007610', 'KYB 3776', 'available', '', 'Honda', 'Accord', 'car', 'Monroe', 41746, '6/8'],
            [64, '5FNRL6H7XRB074334', 'LRD5965', 'available', '', 'Honda', 'Odyssey', 'minivan', 'Monsey', 43654, '3/8'],
            [70, '4T1DAACK4SU041546', 'LNN8127', 'rented', '', 'Toyota', 'Camry', 'car', 'Monroe', 27266, '4/8'],
            [62, '5NMJBCAEXPH276979', 'LKR8773', 'available', '', 'Hyundai', 'Tucson', 'suv', 'Monroe', 43565, '4/8'],
            [65, '5J6RS4H79SL005803', 'LRD5962', 'rented', '', 'Honda', 'CR-V', 'suv', 'Monsey', 25876, '5/8'],
            [66, '7FARS4H76SE009739', 'LRD5961', 'rented', '', 'Honda', 'CR-V', 'suv', 'Monsey', 31724, '4/8'],
            [79, '5J6RS4H72RL010030', 'KMC7467', 'available', '', 'Honda', 'CR-V', 'suv', 'Monsey', 44388, '5/8'],
            [82, 'KM8JCCD16RU190453', 'KVR 7467', 'available', '', 'Hyundai', 'Tucson', 'suv', 'Monroe', 33521, '8/8'],
            [93, '5FNYG1H4XPB043414', 'MAR3494', 'available', '', 'Honda', 'Pilot', 'suv', 'Monsey', 28675, '3/8'],
            [88, '5NMJECAEXPH263639', 'MAR3386', 'rented', '', 'Hyundai', 'Tucson', 'suv', 'Monroe', 37523, '4/8'],
            [95, '5NMJBCDE1RH310399', 'Mar3493', 'rented', '', 'Hyundai', 'Tucson', 'suv', 'Monroe', 15425, '8/8'],
            [96, 'KL4MMBS26PB129577', 'KZC8790', 'rented', '', 'Buick', 'Encore Gx', 'suv', 'Monsey', 12373, '3/8'],
            [69, '4T1DAACK8SU530659', 'LNN 7493', 'rented', '', 'Toyota', 'Camry', 'car', 'Monroe', 29149, '8/8'],
            [99, '5NMS1DAJ5PH561664', 'Mar4090', 'available', '', 'Hyundai', 'Santa Fe', 'suv', 'Monroe', 32919, '3/8'],
            [105, '5NMJECDE8RH383809', 'Mar4868', 'available', '', 'Hyundai', 'Tucson', 'suv', 'Monroe', 26026, '3/8'],
            [106, 'KNDPYDDHXR7144223', 'Mar4854', 'rented', '', 'Kia', 'Sportage', 'suv', 'Monroe', 15851, '6/8'],
            [68, '1HGCY1F36RA072973', 'LRD5774', 'rented', '', 'Honda', 'Accord', 'car', 'Monsey', 33264, '8/8'],
            [100, '5FNRL6H69TB028198', 'MDH8015', 'available', '', 'Honda', 'Odyssey', 'minivan', 'Monroe', 4244, '2/8'],
            [71, '5TDJSKFC3RS115147', 'KUH 8259', 'out_of_service', '', 'Toyota', 'Sienna', 'minivan', 'Monroe', 64748, '1/8'],
            [73, '5FNRL6H64SB035946', 'LUL1197', 'available', '', 'Honda', 'Odyssey', 'minivan', 'Monroe', 21899, '4/8'],
            [74, '5FNRL6H61SB037198', 'LSA2562', 'rented', '', 'Honda', 'Odyssey', 'minivan', 'Monsey', 25333, '4/8'],
            [75, '5FNRL6H61SB036780', 'LTY9159', 'available', '', 'Honda', 'Odyssey', 'minivan', 'Monroe', 25589, '5/8'],
            [76, '5FNRL6H69RB010745', 'LPE4929', 'rented', '', 'Honda', 'Odyssey', 'minivan', 'Monroe', 45221, '1/8'],
            [77, '5FNRL6H6XPB060177', 'LRD5963', 'rented', '', 'Honda', 'Odyssey', 'minivan', 'Monsey', 44054, '2/8'],
            [89, '5TDYRKEC2MS031843', 'KVW6132', 'available', '', 'Toyota', 'Sienna', 'minivan', 'Monroe', 51759, '4/8'],
            [94, '5FNRL6H64SB003353', 'KVP1646', 'available', '', 'Honda', 'Odyssey', 'minivan', 'Monsey', 22751, '2/8'],
            [97, 'KMHLM4DG8RU754474', 'Mar4063', 'rented', '', 'Hyundai', 'Elantra', 'car', 'Monroe', 27315, '2/8'],
            [102, '5FNRL6H64TB016797', 'MCP4451', 'available', '', 'Honda', 'Odyssey', 'minivan', 'Monroe', 4249, '4/8'],
            [103, '5FNRL6H66TB030958', 'MCP4562', 'rented', '', 'Honda', 'Odyssey', 'minivan', 'Monroe', 2624, '3/8'],
            [104, '5FNRL6H66TB008541', 'LKR 7219', 'rented', '', 'Honda', 'Odyssey', 'minivan', 'Monsey', 5068, '5/8'],
            [63, '5N1DR3BC4RC267732', 'LPE4469', 'rented', '', 'Nissan', 'Pathfinder', 'suv', 'Monroe', 15482, '4/8'],
            [78, 'KM8R2DGEXSU906920', 'LUP4684', 'rented', '', 'Hyundai', 'Palisade', 'suv', 'Monsey', 17742, '8/8'],
            [92, 'KM8R7DGE6SU865929', 'Mar 3720', 'available', '', 'Hyundai', 'Palisade', 'suv', 'Monroe', 15390, '4/8'],
            [43, '1FMSK8DH9NGC47027', 'KAL2799', 'out_of_service', '', 'Ford', 'Explorer', 'suv', 'Monsey', 30892, '3/8'],
            [48, '3GNAXKEG7PS162493', 'LAZ6918', 'rented', '', 'Chevrolet', 'Equinox', 'suv', 'Monsey', 36810, '2/8'],
            [49, '3GNAXKEG4PL145998', 'LAZ6919', 'rented', '', 'Chevrolet', 'Equinox', 'suv', 'Monroe', 19004, '3/8'],
            [50, '3GNAXUEG5PS160284', 'LAZ6987', 'available', '', 'Chevrolet', 'Equinox', 'suv', 'Monroe', 20824, '2/8'],
            [51, '1GNSKBKD6PR200070', 'LAZ6871', 'available', '', 'Chevrolet', 'Suburban', 'suv', 'Monroe', 82456, '2/8'],
            [1, '5NMJECDE1RH329204', 'KNM6033', 'rented', '', 'Hyundai', 'Tucson', 'suv', 'Monroe', 29947, '4/8'],
            [2, '5FNRL6H69TB006797', 'MCP4563', 'rented', '', 'Honda', 'Odyssey', 'minivan', 'Monroe', 3165, '2/8'],
        ];

        foreach ($vehicles as $v) {
            [$hqId, $vin, $plate, $status, $_, $make, $model, $class, $location, $odometer, $fuel] = $v;

            // Normalize VIN to uppercase
            $vin = strtoupper($vin);

            // Parse year from VIN position 10 (index 9)
            $yearChar = strtoupper($vin[9] ?? '');
            $year = $vinYearMap[$yearChar] ?? 2024;

            // Map fuel level
            $fuelLevel = $fuelMap[$fuel] ?? '1/2';

            // Daily rate by class
            $dailyRate = $rateMap[$class] ?? 65.00;

            Vehicle::firstOrCreate(
                ['vin' => $vin],
                [
                    'year'          => $year,
                    'make'          => $make,
                    'model'         => $model,
                    'license_plate' => $plate,
                    'vehicle_class' => $class,
                    'status'        => $status,
                    'location_id'   => $locations[$location],
                    'odometer'      => $odometer,
                    'fuel_level'    => $fuelLevel,
                    'daily_rate'    => $dailyRate,
                    'is_active'     => true,
                ]
            );
        }

        $this->command->info('Imported ' . count($vehicles) . ' vehicles from HQ Rentals.');
    }
}
