<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deal;
use App\Support\CaptiveLenders;

/**
 * Wraps MarketCheckService for deal-page consumption: pulls every
 * active OEM offer for a deal's make + zip, normalizes them into
 * {leases, finances, rebates} buckets the calculator can hand-pick from.
 *
 * Costs 1 MarketCheck call per refresh (counted by MarketCheckService).
 */
class MarketCheckOffersService
{
    public function __construct(private readonly MarketCheckService $mc) {}

    /**
     * Returns:
     * [
     *   'ok'           => bool,
     *   'error'        => ?string,
     *   'pulled_at'    => iso8601,
     *   'make'         => 'Honda',
     *   'zip'          => '10952',
     *   'captive'      => 'Honda Financial Services (AHFC)',
     *   'num_found'    => int,
     *   'leases'       => [ ...normalized lease offers ],
     *   'finances'     => [ ...normalized finance offers ],
     *   'rebates'      => [ ...cash offers — pickable as additive rebates ],
     *   'calls_used'   => int,
     *   'calls_remaining' => int,
     * ]
     */
    /**
     * @param array $overrides Optional wizard-form overrides:
     *                         {make?, model?, year?, zip?} — used when the
     *                         user has typed values that aren't yet saved
     *                         on the deal record.
     */
    public function offersForDeal(Deal $deal, ?string $vin = null, array $overrides = []): array
    {
        $listing = null;     // populated when VIN-driven — the actual car at the actual dealer
        $vehicleZip = null;  // zip we'll use for the incentive search (dealer's, when VIN given)

        // VIN path: look up the specific car first so we know exactly which
        // year/make/model AND which dealer has it. That gives us the dealer's
        // zip → MSA, which is what MarketCheck uses for incentive scoping.
        if ($vin && strlen(trim($vin)) === 17) {
            $vinSearch = $this->mc->searchInventory(['vins' => strtoupper(trim($vin)), 'rows' => 1]);
            if (!isset($vinSearch['error']) && !empty($vinSearch['listings'][0])) {
                $listing = $vinSearch['listings'][0];
                $vehicleZip = $listing['dealer']['zip'] ?? null;
            }
            // If VIN didn't match any listing we'll fall through to the
            // deal/override path below — better something than nothing.
        }

        // Resolution order (most specific wins):
        //   1. VIN listing (when matched)
        //   2. wizard form overrides (user just typed)
        //   3. deal saved fields
        //   4. customer saved fields (for ZIP)
        $make = $listing['build']['make']
            ?? ($overrides['make'] ?: null)
            ?? $deal->vehicle_make;

        $zip = $vehicleZip
            ?: ($overrides['zip'] ?: null)
            ?: ($deal->customer_zip ?: optional($deal->customer)->zip);

        if (!$make) return ['ok' => false, 'error' => 'No vehicle make available (set on the deal or supply a VIN).'];
        if (!$zip || !preg_match('/^\d{5}$/', (string) $zip)) {
            return ['ok' => false, 'error' => 'Need a 5-digit ZIP (on the deal, customer, or — when VIN given — the dealer holding the car).'];
        }

        $filters = [];
        $modelForFilter = $listing['build']['model'] ?? ($overrides['model'] ?? null) ?? $deal->vehicle_model;
        $yearForFilter  = $listing['build']['year']  ?? ($overrides['year']  ?? null) ?? $deal->vehicle_year;
        if ($modelForFilter) $filters['model'] = $modelForFilter;
        if ($yearForFilter)  $filters['year']  = (int) $yearForFilter;
        $filters['rows'] = 50;

        $resp = $this->mc->searchIncentivesByMakeZip($make, $zip, $filters);

        if (isset($resp['error'])) {
            return [
                'ok' => false,
                'error' => $resp['error'],
                'calls_used'      => MarketCheckService::callsThisMonth(),
                'calls_remaining' => MarketCheckService::callsRemaining(),
            ];
        }

        $leases   = [];
        $finances = [];
        $rebates  = [];

        foreach (($resp['listings'] ?? []) as $l) {
            $o = $l['offer'] ?? [];
            $type = $o['offer_type'] ?? 'unknown';
            $normalized = $this->normalize($l, $o, $type);

            if ($type === 'lease')        $leases[]   = $normalized;
            elseif ($type === 'finance')  $finances[] = $normalized;
            elseif ($type === 'cash')     $rebates[]  = $normalized;
        }

        // Sort each bucket: cheapest monthly first for lease/finance,
        // biggest cashback first for rebates.
        usort($leases,   fn ($a, $b) => ($a['monthly'] ?? PHP_INT_MAX) <=> ($b['monthly'] ?? PHP_INT_MAX));
        usort($finances, fn ($a, $b) => ($a['monthly'] ?? PHP_INT_MAX) <=> ($b['monthly'] ?? PHP_INT_MAX));
        usort($rebates,  fn ($a, $b) => ($b['cashback'] ?? 0) <=> ($a['cashback'] ?? 0));

        // Merge in custom dealer markdowns scoped to this make/model/year
        // (and the deal's dealer if one is linked). They render in the
        // same Available Rebates picker as OEM rebates so staff sees one list.
        $markdowns = \App\Models\DealerMarkdown::for($make, $deal->vehicle_model, $deal->vehicle_year, $deal->dealer_id)
            ->with('dealer:id,name')
            ->get()
            ->map(fn ($m) => [
                'id'            => 'dm_' . $m->id,
                'type'          => 'cash',
                'source'        => 'dealer_markdown',
                'dealer_markdown_id' => $m->id,
                'title'         => $m->title,
                'cashback'      => (float) $m->amount,
                'target_group'  => $m->dealer ? "Dealer: {$m->dealer->name}" : ($m->dealer_name ? "Dealer: {$m->dealer_name}" : 'Custom dealer markdown'),
                'valid_from'    => optional($m->valid_from)->format('m/d/Y'),
                'valid_through' => optional($m->valid_through)->format('m/d/Y'),
                'vehicle'       => trim(($m->year_from ?? '') . ' ' . ($m->make ?? 'any') . ' ' . ($m->model ?? '')),
                'bullets'       => $m->notes ? [$m->notes] : [],
            ])
            ->all();

        // Dealer markdowns first (they're more lucrative + AutoGo-specific), then OEM cash
        $rebates = array_merge($markdowns, $rebates);

        // If VIN was given AND we found a listing, surface the matched car
        // (exact specs + dealer info) so the wizard can pre-fill from it.
        $matchedListing = null;
        if ($listing) {
            $b = $listing['build'] ?? [];
            $matchedListing = [
                'vin'        => $listing['vin'] ?? null,
                'year'       => $b['year'] ?? null,
                'make'       => $b['make'] ?? null,
                'model'      => $b['model'] ?? null,
                'trim'       => $b['trim'] ?? null,
                'body_type'  => $b['body_type'] ?? null,
                'drivetrain' => $b['drivetrain'] ?? null,
                'fuel_type'  => $b['fuel_type'] ?? null,
                'engine'     => $b['engine'] ?? null,
                'miles'      => $listing['miles'] ?? null,
                'price'      => $listing['price'] ?? null,
                'msrp'       => $listing['msrp'] ?? null,
                'exterior_color' => $listing['exterior_color'] ?? null,
                'stock_no'   => $listing['stock_no'] ?? null,
                'inventory_type' => $listing['inventory_type'] ?? null,
                'photo'      => $listing['media']['photo_links'][0] ?? null,
                'dealer'     => [
                    'name'   => $listing['dealer']['name'] ?? null,
                    'street' => $listing['dealer']['street'] ?? null,
                    'city'   => $listing['dealer']['city'] ?? null,
                    'state'  => $listing['dealer']['state'] ?? null,
                    'zip'    => $listing['dealer']['zip'] ?? null,
                    'phone'  => $listing['dealer']['phone'] ?? null,
                    'website'=> $listing['dealer']['website'] ?? null,
                ],
            ];
        }

        return [
            'ok'              => true,
            'pulled_at'       => now()->toIso8601String(),
            'make'            => $make,
            'zip'             => (string) $zip,
            'captive'         => CaptiveLenders::for($make),
            'num_found'       => ($resp['num_found'] ?? 0) + count($markdowns),
            'num_marketcheck' => $resp['num_found'] ?? 0,
            'num_dealer_markdowns' => count($markdowns),
            'leases'          => $leases,
            'finances'        => $finances,
            'rebates'         => $rebates,
            'matched_listing' => $matchedListing,
            'searched_by_vin' => (bool) $vin,
            'calls_used'      => MarketCheckService::callsThisMonth(),
            'calls_remaining' => MarketCheckService::callsRemaining(),
        ];
    }

    /**
     * Pull the fields we actually need out of MarketCheck's nested shape
     * so the front end isn't grovelling through `listing.offer.amounts[0]`.
     */
    private function normalize(array $listing, array $o, string $type): array
    {
        $vehicle = $o['vehicles'][0] ?? [];
        $amount  = $o['amounts'][0] ?? null;

        $base = [
            'id'             => $listing['id'] ?? null,
            'type'           => $type,
            'title'          => $o['titles'][0] ?? ($o['oem_program_name'] ?? '(unnamed offer)'),
            'program'        => $o['oem_program_name'] ?? null,
            'vehicle'        => trim(($vehicle['year'] ?? '') . ' ' . ($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '') . ' ' . ($vehicle['trim'] ?? '')),
            'msrp'           => isset($o['msrp']) ? (float) $o['msrp'] : null,
            'valid_from'     => $o['valid_from'] ?? null,
            'valid_through'  => $o['valid_through'] ?? null,
            'offer_link'     => $o['offer_link'] ?? null,
            'photo'          => $o['photo_links'][0] ?? null,
            'bullets'        => $o['offers'] ?? [],
            'disclaimer'     => $o['disclaimers'][0] ?? null,
        ];

        if ($type === 'lease') {
            $base['monthly']           = $amount['monthly'] ?? null;
            $base['term']              = $amount['term'] ?? null;
            $base['due_at_signing']    = isset($o['due_at_signing']) ? (float) $o['due_at_signing'] : null;
            $base['down_payment']      = isset($o['down_payment']) ? (float) $o['down_payment'] : null;
            $base['acquisition_fee']   = isset($o['acquisition_fee']) ? (float) $o['acquisition_fee'] : null;
            $base['net_cap_cost']      = isset($o['net_cap_cost']) ? (float) $o['net_cap_cost'] : null;
            $base['residual']          = isset($o['lease_end_purchase_price']) ? (float) $o['lease_end_purchase_price'] : null;
            $base['disposition_fee']   = isset($o['disposition_fee']) ? (float) $o['disposition_fee'] : null;
            $base['mileage_limit']     = $o['mileage_limit'] ?? null;
            $base['over_mileage_fee']  = $o['over_mileage_fee'] ?? null;
            $base['residual_pct']      = $base['residual'] && $base['msrp']
                ? round($base['residual'] / $base['msrp'] * 100, 2)
                : null;
            // Derive money factor from the offer's published numbers.
            // monthly = depreciation/term + (cap+residual)*mf
            //   ⇒ mf = (monthly - (cap-residual)/term) / (cap + residual)
            if ($base['monthly'] && $base['term'] && $base['net_cap_cost'] && $base['residual']) {
                $cap = $base['net_cap_cost']; $res = $base['residual']; $t = $base['term']; $m = $base['monthly'];
                $mf = ($m - ($cap - $res) / $t) / ($cap + $res);
                $base['money_factor_derived'] = $mf > 0 ? round($mf, 6) : null;
                $base['apr_equivalent']       = $mf > 0 ? round($mf * 2400, 3) : null;
            }
        } elseif ($type === 'finance') {
            $base['monthly']     = $amount['monthly'] ?? null;
            $base['term']        = $amount['term'] ?? null;
            $base['apr']         = $o['apr'] ?? null;
            $base['down_payment']= isset($o['down_payment']) ? (float) $o['down_payment'] : null;
        } elseif ($type === 'cash') {
            $base['cashback']        = isset($o['cashback_amount']) ? (float) $o['cashback_amount'] : null;
            $base['target_group']    = $o['cashback_target_group'] ?? null;
        }

        return $base;
    }
}
