<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Dealer;
use App\Models\DealQuote;
use App\Models\Lender;
use App\Services\MarketCheckOffersService;
use App\Services\MarketCheckService;
use App\Support\ZipLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 3-step Quote Create wizard (xDeskPro parity):
 *   1. Quote Structure  ← THIS PR
 *   2. Lender Selection (next PR)
 *   3. Worksheet         (PR after that)
 */
class QuoteWizardController extends Controller
{
    /** Step 1 — Quote Structure form */
    public function step1(Deal $deal)
    {
        $deal->load('customer');

        // Pre-fill from deal + customer, with zip lookup for state/county
        $customerZip = $deal->customer_zip ?: optional($deal->customer)->zip;
        $loc = $customerZip ? ZipLookup::lookup($customerZip) : ['state' => null, 'county' => null, 'confident' => false];

        $prefill = [
            'vehicle' => [
                'vin'    => $deal->vehicle_vin,
                'type'   => 'new',
                'year'   => $deal->vehicle_year,
                'make'   => $deal->vehicle_make,
                'model'  => $deal->vehicle_model,
                'trim'   => $deal->vehicle_trim,
                'odometer' => $deal->vehicle_odometer,
            ],
            'payment_type' => $deal->payment_type ?: 'lease',
            'price' => [
                'cost'       => $deal->cost,
                'profit'     => $deal->profit,
                'sell_price' => $deal->sell_price,
                'msrp'       => $deal->msrp,
                'invoice'    => $deal->invoice_price,
            ],
            'trade' => [
                'allowance' => $deal->trade_allowance,
                'acv'       => $deal->trade_acv,
                'payoff'    => $deal->trade_payoff,
                'owned_or_leased' => $deal->trade_is_leased ? 'leased' : 'owned',
            ],
            'customer' => [
                'zip'    => $customerZip,
                'state'  => $loc['state'],
                'county' => $loc['county'],
            ],
            'dealer' => [
                'zip' => null, // pulled from settings in a follow-up
            ],
            'drive_off' => [
                'type'   => 'total_drive_off',
                'amount' => $deal->drive_off,
            ],
            'lease' => [
                'terms'                => [$deal->term ?: 36],
                'mileage_per_year'     => $deal->mileage_per_year ?: 10000,
                'acquisition_fee_type' => 'capped',
                'lender_loyalty'       => false,
            ],
            'credit' => [
                'score' => $deal->credit_score,
            ],
            'applied_rebate_ids' => [],
        ];

        return Inertia::render('Leasing/Deals/Quotes/Wizard', [
            'deal'    => $deal,
            'prefill' => $prefill,
            'lenders' => Lender::active()->get(['id', 'name']),
        ]);
    }

    /** ZIP → {state, county} JSON — front-end calls this on blur */
    public function lookupZip(Request $r): JsonResponse
    {
        $zip = $r->string('zip')->toString();
        return response()->json(ZipLookup::lookup($zip));
    }

    /** VIN decode → MarketCheck (1 call) */
    public function decodeVin(Request $r, MarketCheckService $mc): JsonResponse
    {
        $vin = strtoupper(trim($r->string('vin')->toString()));
        $result = $mc->decodeVin($vin);
        return response()->json($result);
    }

    /**
     * Pull live OEM offers. Optional `vin` body param: if given, MarketCheck
     * looks up the exact car first, identifies the dealer holding it, then
     * scopes incentives to the dealer's MSA. Otherwise falls back to the
     * deal's make + customer ZIP.
     */
    public function pullOffers(Request $r, Deal $deal, MarketCheckOffersService $offers): JsonResponse
    {
        $vin = $r->string('vin')->toString() ?: null;
        return response()->json($offers->offersForDeal($deal, $vin));
    }

    /**
     * Browse a specific dealer's inventory via MarketCheck. Costs 1 call.
     * Body: { dealer_id?, dealer_name?, zip?, make?, model?, year?,
     *         inventory_type? ("new"|"used"|"certified"),
     *         price_range?, miles_range?, rows? }
     */
    public function browseInventory(Request $r, MarketCheckService $mc): JsonResponse
    {
        $data = $r->validate([
            'dealer_id'      => 'nullable|exists:dealers,id',
            'dealer_name'    => 'nullable|string|max:120',
            'zip'            => 'nullable|string|size:5',
            'make'           => 'nullable|string|max:60',
            'model'          => 'nullable|string|max:60',
            'year'           => 'nullable|integer|min:1990|max:2099',
            'inventory_type' => 'nullable|in:new,used,certified',
            'price_range'    => 'nullable|string|max:30',
            'miles_range'    => 'nullable|string|max:30',
            'rows'           => 'nullable|integer|min:1|max:50',
        ]);

        // Resolve dealer name + zip from a known dealer record if user picked one
        if (!empty($data['dealer_id'])) {
            $dealer = \App\Models\Dealer::find($data['dealer_id']);
            if ($dealer) {
                $data['dealer_name'] = $data['dealer_name'] ?: $dealer->name;
                $data['zip']         = $data['zip']         ?: $dealer->zip;
            }
        }

        if (empty($data['dealer_name']) && empty($data['zip'])) {
            return response()->json(['ok' => false, 'error' => 'Need a dealer (or at least a ZIP) to search inventory.']);
        }

        $filters = [
            'rows' => $data['rows'] ?? 25,
        ];
        if (!empty($data['dealer_name']))    $filters['seller_name']    = $data['dealer_name'];
        if (!empty($data['zip']))            $filters['zip']            = $data['zip'];
        if (!empty($data['make']))           $filters['make']           = $data['make'];
        if (!empty($data['model']))          $filters['model']          = $data['model'];
        if (!empty($data['year']))           $filters['year']           = $data['year'];
        if (!empty($data['inventory_type']))$filters['inventory_type']  = $data['inventory_type'];
        if (!empty($data['price_range']))   $filters['price_range']     = $data['price_range'];
        if (!empty($data['miles_range']))   $filters['miles_range']     = $data['miles_range'];

        $result = $mc->searchInventory($filters);
        if (isset($result['error'])) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'],
                'calls_used'      => MarketCheckService::callsThisMonth(),
                'calls_remaining' => MarketCheckService::callsRemaining(),
            ]);
        }

        // Normalize each listing into something the wizard can pick from
        $cars = collect($result['listings'] ?? [])->map(function ($l) {
            $b = $l['build'] ?? [];
            return [
                'id'         => $l['id'] ?? null,
                'vin'        => $l['vin'] ?? null,
                'year'       => $b['year'] ?? null,
                'make'       => $b['make'] ?? null,
                'model'      => $b['model'] ?? null,
                'trim'       => $b['trim'] ?? null,
                'body_type'  => $b['body_type'] ?? null,
                'drivetrain' => $b['drivetrain'] ?? null,
                'transmission'=> $b['transmission'] ?? null,
                'fuel_type'  => $b['fuel_type'] ?? null,
                'engine'     => $b['engine'] ?? null,
                'miles'      => $l['miles'] ?? null,
                'price'      => $l['price'] ?? null,
                'msrp'       => $l['msrp'] ?? null,
                'exterior_color' => $l['exterior_color'] ?? null,
                'interior_color' => $l['interior_color'] ?? null,
                'inventory_type' => $l['inventory_type'] ?? null,
                'stock_no'   => $l['stock_no'] ?? null,
                'dealer'     => [
                    'name'   => $l['dealer']['name'] ?? null,
                    'street' => $l['dealer']['street'] ?? null,
                    'city'   => $l['dealer']['city'] ?? null,
                    'state'  => $l['dealer']['state'] ?? null,
                    'zip'    => $l['dealer']['zip'] ?? null,
                    'phone'  => $l['dealer']['phone'] ?? null,
                    'website'=> $l['dealer']['website'] ?? null,
                ],
                'photo'      => $l['media']['photo_links'][0] ?? null,
                'vdp_url'    => $l['vdp_url'] ?? null,
            ];
        })->all();

        return response()->json([
            'ok'              => true,
            'num_found'       => $result['num_found'] ?? 0,
            'cars'            => $cars,
            'filters'         => $filters,
            'calls_used'      => MarketCheckService::callsThisMonth(),
            'calls_remaining' => MarketCheckService::callsRemaining(),
        ]);
    }

    /**
     * Save Quote Structure as a draft DealQuote (one per selected term).
     * Returns the IDs so the next step can iterate them.
     */
    public function store(Request $r, Deal $deal)
    {
        $data = $r->validate([
            'payment_type'                  => 'required|in:lease,one_pay,finance,balloon,cash',
            'vehicle'                       => 'required|array',
            'vehicle.vin'                   => 'nullable|string|max:17',
            'vehicle.type'                  => 'nullable|in:new,used,certified',
            'vehicle.year'                  => 'nullable|integer|min:1990|max:2099',
            'vehicle.make'                  => 'nullable|string|max:60',
            'vehicle.model'                 => 'nullable|string|max:60',
            'vehicle.trim'                  => 'nullable|string|max:60',
            'vehicle.odometer'              => 'nullable|integer|min:0',
            'price'                         => 'required|array',
            'price.cost'                    => 'nullable|numeric|min:0',
            'price.profit'                  => 'nullable|numeric',
            'price.sell_price'              => 'nullable|numeric|min:0',
            'price.msrp'                    => 'nullable|numeric|min:0',
            'price.invoice'                 => 'nullable|numeric|min:0',
            'trade'                         => 'nullable|array',
            'customer'                      => 'nullable|array',
            'customer.zip'                  => 'nullable|string|max:10',
            'customer.state'                => 'nullable|string|max:2',
            'customer.county'               => 'nullable|string|max:60',
            'dealer'                        => 'nullable|array',
            'dealer.zip'                    => 'nullable|string|max:10',
            'drive_off'                     => 'nullable|array',
            'drive_off.type'                => 'nullable|in:total_drive_off,lease_cap_reduction,sign_and_drive',
            'drive_off.amount'              => 'nullable|numeric|min:0',
            'lease'                         => 'nullable|array',
            'lease.terms'                   => 'nullable|array',
            'lease.terms.*'                 => 'integer|min:6|max:96',
            'lease.mileage_per_year'        => 'nullable|integer|min:5000|max:30000',
            'lease.acquisition_fee_type'    => 'nullable|in:upfront,capped',
            'lease.lender_loyalty'          => 'nullable|boolean',
            'credit'                        => 'nullable|array',
            'credit.score'                  => 'nullable|integer|min:300|max:850',
            'applied_rebate_ids'            => 'nullable|array',
            'applied_rebate_ids.*'          => 'string',
            'rebates_total'                 => 'nullable|numeric|min:0',
        ]);

        // Persist deal-level fields that have first-class columns
        $deal->update(array_filter([
            'vehicle_vin'      => $data['vehicle']['vin'] ?? null,
            'vehicle_year'     => $data['vehicle']['year'] ?? null,
            'vehicle_make'     => $data['vehicle']['make'] ?? null,
            'vehicle_model'    => $data['vehicle']['model'] ?? null,
            'vehicle_trim'     => $data['vehicle']['trim'] ?? null,
            'vehicle_odometer' => $data['vehicle']['odometer'] ?? null,
            'payment_type'     => $data['payment_type'],
            'cost'             => $data['price']['cost'] ?? null,
            'sell_price'       => $data['price']['sell_price'] ?? null,
            'msrp'             => $data['price']['msrp'] ?? null,
            'invoice_price'    => $data['price']['invoice'] ?? null,
            'profit'           => $data['price']['profit'] ?? null,
            'trade_allowance'  => $data['trade']['allowance'] ?? null,
            'trade_acv'        => $data['trade']['acv'] ?? null,
            'trade_payoff'     => $data['trade']['payoff'] ?? null,
            'trade_is_leased'  => isset($data['trade']['owned_or_leased']) ? $data['trade']['owned_or_leased'] === 'leased' : null,
            'customer_zip'     => $data['customer']['zip'] ?? null,
            'mileage_per_year' => $data['lease']['mileage_per_year'] ?? null,
            'drive_off'        => $data['drive_off']['amount'] ?? null,
            'credit_score'     => $data['credit']['score'] ?? null,
        ], fn ($v) => $v !== null));

        // One DealQuote draft per selected term — keeps multi-term natural
        $terms = $data['lease']['terms'] ?? [($data['lease']['terms'][0] ?? 36)];
        if (empty($terms)) $terms = [36];

        $createdIds = [];
        foreach ($terms as $term) {
            $quote = DealQuote::create([
                'deal_id'              => $deal->id,
                'payment_type'         => $data['payment_type'],
                'term'                 => $term,
                'mileage_per_year'     => $data['lease']['mileage_per_year'] ?? 10000,
                'sell_price'           => $data['price']['sell_price'] ?? null,
                'msrp'                 => $data['price']['msrp'] ?? null,
                'rebates'              => $data['rebates_total'] ?? 0,
                'acquisition_fee_type' => $data['lease']['acquisition_fee_type'] ?? 'capped',
                'is_draft'             => true,
                'is_selected'          => false,
                'created_by'           => auth()->id(),
                'structure'            => $data,
            ]);
            $createdIds[] = $quote->id;
        }

        // Step 2 (Lender Selection) is a future PR — for now, kick back to the deal Show page.
        return redirect()->route('leasing.deals.show', $deal)
            ->with('success', count($createdIds) === 1
                ? "Quote #{$createdIds[0]} drafted (1 term). Lender Selection step coming soon."
                : count($createdIds) . " draft quotes created (one per term). Lender Selection step coming soon.");
    }
}
