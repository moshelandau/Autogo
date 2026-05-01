<?php

declare(strict_types=1);

namespace App\Support;

/**
 * NY State + county sales tax rates (2026).
 *
 * Source: NYS Department of Taxation and Finance Publication 718 — total
 * combined rate (state 4% + county/local).
 *
 * For lease taxation, NY taxes the MONTHLY PAYMENT at the customer's
 * residential combined rate (not the dealer's). Dealer ZIP is used for
 * dealer fees, not sales tax.
 *
 * Counties NOT in this map fall back to the state-only rate of 4.0%
 * (rare — covers gaps if we miss a county).
 */
class NyCountyTax
{
    /** county name (case-insensitive) → combined rate (state + county/local). */
    private const RATES = [
        // NYC — Five Boroughs (8.875% = 4% state + 4.5% NYC + 0.375% MCTD)
        'new york'    => 0.08875,
        'kings'       => 0.08875,
        'queens'      => 0.08875,
        'bronx'       => 0.08875,
        'richmond'    => 0.08875, // Staten Island
        // NYC suburbs (MCTD area)
        'nassau'      => 0.08625,
        'suffolk'     => 0.08625,
        'westchester' => 0.08375,
        'rockland'    => 0.08375,
        'orange'      => 0.08125,
        'putnam'      => 0.08375,
        'dutchess'    => 0.08125,
        // Capital region
        'albany'      => 0.08000,
        'schenectady' => 0.08000,
        'rensselaer'  => 0.08000,
        'saratoga'    => 0.07000,
        'columbia'    => 0.08000,
        'greene'      => 0.08000,
        'ulster'      => 0.08000,
        // Mid-Hudson / Catskills
        'sullivan'    => 0.08000,
        'delaware'    => 0.08000,
        // Western NY
        'erie'        => 0.08750,
        'monroe'      => 0.08000, // Rochester area (NOT to be confused with Rockland/Orange's "Monroe" town)
        'niagara'     => 0.08000,
        'onondaga'    => 0.08000, // Syracuse
        'wayne'       => 0.08000,
        'genesee'     => 0.08000,
        'orleans'     => 0.08000,
        'wyoming'     => 0.08000,
        'allegany'    => 0.08500,
        'cattaraugus' => 0.08000,
        'chautauqua'  => 0.08000,
        // Southern Tier
        'broome'      => 0.08000,
        'tioga'       => 0.08000,
        'chemung'     => 0.08000,
        'steuben'     => 0.08000,
        'tompkins'    => 0.08000,
        'cortland'    => 0.08000,
        'chenango'    => 0.08000,
        // Central / North Country
        'oneida'      => 0.08750,
        'madison'     => 0.08000,
        'oswego'      => 0.08000,
        'jefferson'   => 0.08000,
        'lewis'       => 0.08000,
        'st lawrence' => 0.08000,
        'st. lawrence'=> 0.08000,
        'franklin'    => 0.08000,
        'clinton'     => 0.08000,
        'essex'       => 0.08000,
        'warren'      => 0.07000,
        'washington'  => 0.07000,
        'hamilton'    => 0.08000,
        'fulton'      => 0.08000,
        'herkimer'    => 0.08250,
        'montgomery'  => 0.08000,
        'otsego'      => 0.08000,
        'schoharie'   => 0.08000,
        'schuyler'    => 0.08000,
        'seneca'      => 0.08000,
        'cayuga'      => 0.08000,
        'livingston'  => 0.08000,
        'ontario'     => 0.07500,
        'yates'       => 0.08000,
    ];

    private const STATE_FALLBACK = 0.04000;

    /**
     * Look up the combined rate for a NY county. Returns the state-only
     * rate (4%) if not found and `confident` flag indicates whether the
     * lookup hit the table.
     *
     * Handles common variants: handles "Monroe, Orange" (xDeskPro
     * concatenated label — picks the FIRST listed county), strips
     * "County" suffix, case-insensitive.
     */
    public static function lookup(?string $countyName): array
    {
        $county = strtolower(trim((string) $countyName));
        if ($county === '') return ['rate' => self::STATE_FALLBACK, 'county' => null, 'confident' => false];

        // Handle "Monroe, Orange" → pick first; strip " county" suffix
        $county = trim(explode(',', $county)[0]);
        $county = preg_replace('/\s+county$/', '', $county);

        $rate = self::RATES[$county] ?? self::STATE_FALLBACK;
        return [
            'rate'      => $rate,
            'county'    => $countyName,
            'confident' => isset(self::RATES[$county]),
        ];
    }

    /** Convenience for ZipLookup integration — returns rate by ZIP. */
    public static function lookupByZip(string $zip): array
    {
        $loc = ZipLookup::lookup($zip);
        if ($loc['state'] !== 'NY' || !$loc['county']) {
            return ['rate' => self::STATE_FALLBACK, 'county' => $loc['county'], 'state' => $loc['state'], 'confident' => false];
        }
        $tax = self::lookup($loc['county']);
        return $tax + ['state' => 'NY', 'zip' => $zip];
    }
}
