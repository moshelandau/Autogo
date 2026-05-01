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
    public function offersForDeal(Deal $deal): array
    {
        $make = $deal->vehicle_make;
        $zip  = $deal->customer_zip ?: optional($deal->customer)->zip;

        if (!$make) return ['ok' => false, 'error' => 'Deal has no vehicle make set.'];
        if (!$zip || !preg_match('/^\d{5}$/', (string) $zip)) {
            return ['ok' => false, 'error' => 'Need a 5-digit ZIP on the deal or its customer.'];
        }

        $filters = [];
        if ($deal->vehicle_model) $filters['model'] = $deal->vehicle_model;
        if ($deal->vehicle_year)  $filters['year']  = (int) $deal->vehicle_year;
        $filters['rows'] = 50; // generous so staff sees all options at once

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

        return [
            'ok'              => true,
            'pulled_at'       => now()->toIso8601String(),
            'make'            => $make,
            'zip'             => (string) $zip,
            'captive'         => CaptiveLenders::for($make),
            'num_found'       => $resp['num_found'] ?? 0,
            'leases'          => $leases,
            'finances'        => $finances,
            'rebates'         => $rebates,
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
