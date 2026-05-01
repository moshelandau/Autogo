<?php

declare(strict_types=1);

namespace App\Support;

/**
 * OEM → captive lender lookup.
 *
 * MarketCheck's incentive payload doesn't include a structured
 * `lender_name` field — the bank is buried in the disclaimer text.
 * In practice the captive lender for each OEM is essentially fixed,
 * so this map gets us the right bank in one lookup. Non-captive
 * outliers (Chase, Ally, US Bank) still need a regex pass over the
 * disclaimer; that's a follow-up.
 */
class CaptiveLenders
{
    /** make (lowercase) → display name */
    private const MAP = [
        'acura'         => 'Acura Financial Services (AHFC)',
        'audi'          => 'Audi Financial Services',
        'bmw'           => 'BMW Financial Services',
        'buick'         => 'GM Financial',
        'cadillac'      => 'GM Financial',
        'chevrolet'     => 'GM Financial',
        'chevy'         => 'GM Financial',
        'chrysler'      => 'Stellantis Financial Services',
        'dodge'         => 'Stellantis Financial Services',
        'ford'          => 'Ford Credit',
        'genesis'       => 'Genesis Finance',
        'gmc'           => 'GM Financial',
        'honda'         => 'Honda Financial Services (AHFC)',
        'hyundai'       => 'Hyundai Motor Finance',
        'infiniti'      => 'Infiniti Financial Services',
        'jaguar'        => 'Chase Auto',
        'jeep'          => 'Stellantis Financial Services',
        'kia'           => 'Kia Motors Finance',
        'land rover'    => 'Chase Auto',
        'lexus'         => 'Lexus Financial Services (TFS)',
        'lincoln'       => 'Lincoln Automotive Financial Services',
        'mazda'         => 'Mazda Financial Services (Toyota)',
        'mercedes-benz' => 'Mercedes-Benz Financial Services',
        'mercedes'      => 'Mercedes-Benz Financial Services',
        'mini'          => 'BMW Financial Services',
        'mitsubishi'    => 'Ally Financial',
        'nissan'        => 'Nissan Motor Acceptance (NMAC)',
        'porsche'       => 'Porsche Financial Services',
        'ram'           => 'Stellantis Financial Services',
        'subaru'        => 'Chase Auto',
        'tesla'         => 'Tesla Finance',
        'toyota'        => 'Toyota Financial Services (TFS)',
        'volkswagen'    => 'Volkswagen Credit (VCI)',
        'volvo'         => 'Volvo Car Financial Services',
    ];

    public static function for(string $make): ?string
    {
        return self::MAP[strtolower(trim($make))] ?? null;
    }
}
