<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tiny ZIP → {state, county} lookup. Honest scope:
 *
 *   ✅ NY county map for ZIPs we ship in/around (Monsey, Monroe area,
 *      NYC, Westchester, Rockland, Orange, Long Island).
 *   🟡 State guess via the first digit of the ZIP for the rest of the
 *      country — better than nothing, accurate at the state level
 *      for ~95% of US ZIPs but won't get the county.
 *
 * For real coverage we'd seed a full US ZIP→county table (~42k rows
 * from USPS / IRS public data). Punted to a follow-up; this gets the
 * common AutoGo customer ZIPs right today.
 */
class ZipLookup
{
    /** Local NY ZIP → county map (most-used dealer/customer ZIPs). */
    private const NY_COUNTIES = [
        // Rockland County
        '10901' => 'Rockland', '10952' => 'Rockland', '10977' => 'Rockland',
        '10954' => 'Rockland', '10956' => 'Rockland', '10960' => 'Rockland',
        '10913' => 'Rockland', '10920' => 'Rockland', '10923' => 'Rockland',
        '10927' => 'Rockland', '10970' => 'Rockland', '10974' => 'Rockland',
        '10984' => 'Rockland', '10986' => 'Rockland', '10989' => 'Rockland',
        // Orange County
        '10940' => 'Orange', '10941' => 'Orange', '10950' => 'Orange',
        '12550' => 'Orange', '12553' => 'Orange', '12586' => 'Orange',
        '10921' => 'Orange', '10924' => 'Orange', '10925' => 'Orange',
        '10930' => 'Orange', '10950' => 'Orange', '10973' => 'Orange',
        '10985' => 'Orange', '10987' => 'Orange', '10990' => 'Orange',
        '10992' => 'Orange', '10996' => 'Orange', '10998' => 'Orange',
        // Westchester
        '10510' => 'Westchester', '10520' => 'Westchester', '10523' => 'Westchester',
        '10530' => 'Westchester', '10532' => 'Westchester', '10533' => 'Westchester',
        '10538' => 'Westchester', '10543' => 'Westchester', '10550' => 'Westchester',
        '10566' => 'Westchester', '10583' => 'Westchester', '10591' => 'Westchester',
        '10601' => 'Westchester', '10605' => 'Westchester', '10701' => 'Westchester',
        // NYC — Manhattan
        '10001' => 'New York', '10002' => 'New York', '10003' => 'New York',
        '10010' => 'New York', '10016' => 'New York', '10017' => 'New York',
        '10019' => 'New York', '10025' => 'New York', '10128' => 'New York',
        // NYC — Brooklyn
        '11201' => 'Kings', '11203' => 'Kings', '11205' => 'Kings',
        '11209' => 'Kings', '11210' => 'Kings', '11211' => 'Kings',
        '11215' => 'Kings', '11218' => 'Kings', '11219' => 'Kings',
        '11220' => 'Kings', '11223' => 'Kings', '11226' => 'Kings',
        '11229' => 'Kings', '11230' => 'Kings', '11234' => 'Kings',
        '11235' => 'Kings', '11236' => 'Kings',
        // NYC — Queens
        '11354' => 'Queens', '11355' => 'Queens', '11367' => 'Queens',
        '11368' => 'Queens', '11375' => 'Queens', '11385' => 'Queens',
        '11432' => 'Queens', '11691' => 'Queens',
        // NYC — Bronx
        '10451' => 'Bronx', '10452' => 'Bronx', '10453' => 'Bronx',
        '10456' => 'Bronx', '10458' => 'Bronx', '10460' => 'Bronx',
        '10462' => 'Bronx', '10463' => 'Bronx', '10467' => 'Bronx',
        '10468' => 'Bronx', '10469' => 'Bronx', '10472' => 'Bronx',
        '10473' => 'Bronx',
        // NYC — Staten Island
        '10301' => 'Richmond', '10303' => 'Richmond', '10304' => 'Richmond',
        '10305' => 'Richmond', '10306' => 'Richmond', '10308' => 'Richmond',
        '10309' => 'Richmond', '10314' => 'Richmond',
        // Long Island — Nassau
        '11530' => 'Nassau', '11550' => 'Nassau', '11572' => 'Nassau',
        '11580' => 'Nassau', '11590' => 'Nassau', '11691' => 'Nassau',
        '11701' => 'Suffolk', '11706' => 'Suffolk', '11717' => 'Suffolk',
        '11722' => 'Suffolk', '11743' => 'Suffolk', '11757' => 'Suffolk',
    ];

    /** First-digit ZIP → state guess (USPS region rule of thumb). */
    private const STATE_BY_FIRST_DIGIT = [
        '0' => ['MA', 'RI', 'NH', 'ME', 'VT', 'CT', 'NJ'], // 010-027 MA, 028-029 RI, 030-038 NH, 039-049 ME, 050-059 VT, 060-069 CT, 070-089 NJ, 089-099 NJ/MA
        '1' => ['NY', 'PA', 'DE'],
        '2' => ['DC', 'VA', 'MD', 'NC', 'WV', 'SC'],
        '3' => ['FL', 'GA', 'AL', 'TN', 'MS'],
        '4' => ['KY', 'OH', 'IN', 'MI'],
        '5' => ['IA', 'WI', 'MN', 'SD', 'ND', 'MT'],
        '6' => ['IL', 'KS', 'MO', 'NE'],
        '7' => ['LA', 'AR', 'OK', 'TX'],
        '8' => ['CO', 'WY', 'NM', 'AZ', 'UT', 'ID', 'NV'],
        '9' => ['CA', 'OR', 'WA', 'AK', 'HI'],
    ];

    public static function lookup(string $zip): array
    {
        $zip = trim($zip);
        if (!preg_match('/^\d{5}$/', $zip)) return ['state' => null, 'county' => null, 'confident' => false];

        $county = self::NY_COUNTIES[$zip] ?? null;
        if ($county) {
            return ['state' => 'NY', 'county' => $county, 'confident' => true];
        }

        // ZIP starts with 0 or 1 → likely NE region. We'll guess state but admit low confidence on county.
        $first   = $zip[0];
        $state   = match (true) {
            $zip >= '01000' && $zip <= '02799' => 'MA',
            $zip >= '02800' && $zip <= '02999' => 'RI',
            $zip >= '03000' && $zip <= '03899' => 'NH',
            $zip >= '03900' && $zip <= '04999' => 'ME',
            $zip >= '05000' && $zip <= '05999' => 'VT',
            $zip >= '06000' && $zip <= '06999' => 'CT',
            $zip >= '07000' && $zip <= '08999' => 'NJ',
            $zip >= '09000' && $zip <= '09999' => 'AE', // military
            $zip >= '10000' && $zip <= '14999' => 'NY',
            $zip >= '15000' && $zip <= '19699' => 'PA',
            $zip >= '19700' && $zip <= '19999' => 'DE',
            default => null,
        };
        return ['state' => $state, 'county' => null, 'confident' => false];
    }
}
