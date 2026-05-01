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

    /** ZIP → {state, county, tax_rate} JSON — front-end calls this on blur */
    public function lookupZip(Request $r): JsonResponse
    {
        $zip = $r->string('zip')->toString();
        $loc = ZipLookup::lookup($zip);
        // Layer NY county tax rate when state is NY
        $taxRate = null;
        if (($loc['state'] ?? null) === 'NY') {
            $taxRate = \App\Support\NyCountyTax::lookup($loc['county'])['rate'] ?? null;
        }
        return response()->json($loc + ['tax_rate' => $taxRate]);
    }

    /** VIN decode → MarketCheck (1 call) */
    public function decodeVin(Request $r, MarketCheckService $mc): JsonResponse
    {
        $vin = strtoupper(trim($r->string('vin')->toString()));
        $result = $mc->decodeVin($vin);
        return response()->json($result);
    }

    /**
     * Pull live OEM offers. Body params (all optional — sent by the wizard
     * to reflect the user's CURRENT form state, which may not yet be saved
     * to the deal record):
     *   - vin       — if given, MarketCheck looks up the exact car +
     *                 dealer first and scopes incentives to that MSA
     *   - make      — overrides deal->vehicle_make
     *   - model     — overrides deal->vehicle_model
     *   - year      — overrides deal->vehicle_year
     *   - zip       — overrides deal->customer_zip / customer->zip
     *
     * Without overrides, falls back to the deal's saved fields (legacy
     * Deal Show calculator path).
     */
    public function pullOffers(Request $r, Deal $deal, MarketCheckOffersService $offers): JsonResponse
    {
        $overrides = $r->validate([
            'vin'    => 'nullable|string|size:17',
            'make'   => 'nullable|string|max:60',
            'model'  => 'nullable|string|max:60',
            'year'   => 'nullable|integer|min:1990|max:2099',
            'zip'    => 'nullable|string|size:5',
            'narrow' => 'nullable|boolean', // true → also filter by model+year (focused view)
        ]);
        $vin = $overrides['vin'] ?? null;
        return response()->json($offers->offersForDeal($deal, $vin, $overrides));
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
     * Step 2 — Lender Selection.
     * Lists draft quotes (one per term from step 1) and the lender
     * programs that fit each (filtered by make/model/term/credit tier).
     */
    public function step2(Deal $deal, MarketCheckOffersService $offersService)
    {
        $deal->load('customer');

        $drafts = $deal->quotes()
            ->where('is_draft', true)
            ->whereNull('lender_id')
            ->orderByDesc('id')
            ->get();

        if ($drafts->isEmpty()) {
            return redirect()->route('leasing.deals.quotes.wizard', $deal)
                ->with('error', 'No draft quotes to assign a lender to. Start at step 1 first.');
        }

        $terms = $drafts->pluck('term')->unique()->all();
        $programs = \App\Models\LenderProgram::query()
            ->where('is_active', true)
            ->where(function ($q) use ($deal) {
                $q->whereNull('make')->orWhereRaw('LOWER(make) = ?', [strtolower($deal->vehicle_make ?? '')]);
            })
            ->where(function ($q) use ($deal) {
                $q->whereNull('model')->orWhereRaw('LOWER(model) = ?', [strtolower($deal->vehicle_model ?? '')]);
            })
            ->whereIn('term', $terms)
            ->where(function ($q) use ($deal) {
                $q->whereNull('min_credit_score')->orWhere('min_credit_score', '<=', $deal->credit_score ?? 850);
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now());
            })
            ->with('lender:id,name')
            ->orderBy('apr')
            ->orderBy('money_factor')
            ->get();

        // Pull MarketCheck lease + finance offers for this deal so we can
        // surface them as additional pickable options alongside our internal
        // Lender Programs. Costs 1 API call per Step 2 view.
        $mcOffers = $offersService->offersForDeal($deal);
        $marketCheckOffers = $mcOffers['ok'] ?? false
            ? array_merge(
                array_map(fn ($o) => $o + ['kind' => 'lease'],   $mcOffers['leases']   ?? []),
                array_map(fn ($o) => $o + ['kind' => 'finance'], $mcOffers['finances'] ?? []),
            )
            : [];

        return Inertia::render('Leasing/Deals/Quotes/WizardStep2', [
            'deal'              => $deal,
            'drafts'            => $drafts,
            'programs'          => $programs,
            'lenders'           => Lender::active()->get(['id', 'name']),
            'marketCheckOffers' => $marketCheckOffers,
            'marketCheckCaptive'=> $mcOffers['captive'] ?? null,
            'marketCheckOk'     => $mcOffers['ok'] ?? false,
            'marketCheckError'  => $mcOffers['error'] ?? null,
        ]);
    }

    /**
     * Step 2 save — apply lender + program to each draft, then bump them
     * toward the worksheet (still draft until step 3 finalizes).
     */
    public function step2Save(Request $r, Deal $deal)
    {
        $data = $r->validate([
            'assignments'                  => 'required|array',
            'assignments.*.draft_id'       => 'required|exists:deal_quotes,id',
            'assignments.*.lender_id'      => 'nullable|exists:lenders,id',
            'assignments.*.program_id'     => 'nullable|exists:lender_programs,id',
            'assignments.*.money_factor'   => 'nullable|numeric|min:0',
            'assignments.*.apr'            => 'nullable|numeric|min:0',
        ]);

        foreach ($data['assignments'] as $a) {
            $draft = DealQuote::where('id', $a['draft_id'])->where('deal_id', $deal->id)->first();
            if (!$draft) continue;

            $update = ['lender_id' => $a['lender_id'] ?? null];

            if (!empty($a['program_id'])) {
                $program = \App\Models\LenderProgram::find($a['program_id']);
                if ($program) {
                    $update['lender_id']    = $update['lender_id'] ?: $program->lender_id;
                    $update['money_factor'] = $program->money_factor;
                    $update['apr']          = $program->apr;
                    $update['residual_value'] = $program->residual_pct && $draft->msrp
                        ? round((float) $draft->msrp * (float) $program->residual_pct / 100, 2)
                        : null;
                    $update['acquisition_fee'] = $program->acquisition_fee ?? $draft->acquisition_fee;
                }
            }

            if (!empty($a['money_factor'])) $update['money_factor'] = $a['money_factor'];
            if (!empty($a['apr']))          $update['apr']          = $a['apr'];

            $draft->update($update);
        }

        return redirect()->route('leasing.deals.quotes.wizard.step3', $deal)
            ->with('success', 'Lender(s) assigned. Now finalize the worksheet →');
    }

    /**
     * Step 3 — Worksheet. Renders the per-draft worksheet form with
     * upfront/capped fee toggles, buy/sell rate, base+adjusted residual,
     * and live-computed monthly/profit numbers.
     */
    public function step3(Deal $deal)
    {
        $deal->load('customer');
        $drafts = $deal->quotes()
            ->where('is_draft', true)
            ->whereNotNull('lender_id')
            ->with('lender:id,name')
            ->orderByDesc('id')
            ->get();

        if ($drafts->isEmpty()) {
            return redirect()->route('leasing.deals.quotes.wizard.step2', $deal)
                ->with('error', 'Pick a lender for at least one draft first.');
        }

        // Look up the customer's county tax rate so the worksheet pre-fills
        // with the right number instead of a generic 8.125%.
        $customerZip = $deal->customer_zip ?: optional($deal->customer)->zip;
        $taxByZip = $customerZip
            ? \App\Support\NyCountyTax::lookupByZip($customerZip)
            : ['rate' => 0.08125, 'county' => null];

        $svc = app(\App\Services\LeaseWorksheet::class);
        $sheets = $drafts->mapWithKeys(function ($d) use ($svc, $deal, $taxByZip) {
            // Pull applied rebates (full objects with title/source/amount) from
            // the draft's structure JSONB — persisted at step 1.
            $appliedRebates = $d->structure['applied_rebates'] ?? [];

            $defaults = [
                'cost' => $deal->cost,
                'rebates' => (float) ($d->rebates ?? 0),
                'applied_rebates' => $appliedRebates,
                'fees' => [
                    ['name' => 'Acquisition Fee', 'amount' => (float) ($d->acquisition_fee ?? 595), 'paid_as' => $d->acquisition_fee_type ?? 'capped'],
                    ['name' => 'Doc Fee',         'amount' => 199, 'paid_as' => 'capped'],
                    ['name' => 'Registration',    'amount' => 175, 'paid_as' => 'capped'],
                    ['name' => 'Inspection',      'amount' => 0,   'paid_as' => 'upfront'],
                    ['name' => 'Tire Fee',        'amount' => 0,   'paid_as' => 'upfront'],
                ],
                'taxes_paid_as' => 'capped',
                'tax_rate'      => $taxByZip['rate'],
                'tax_county'    => $taxByZip['county'],
                'sell_money_factor' => $d->money_factor,
                'buy_money_factor'  => $d->money_factor,
                'base_residual_pct' => $d->msrp ? round((float) $d->residual_value / (float) $d->msrp * 100, 2) : 0,
                'adj_residual_pct'  => 0,
                'max_advance_pct'   => 115,
                'purchase_option_fee' => 0,
            ];
            return [$d->id => [
                'inputs'  => $defaults,
                'result'  => $svc->compute($d, $defaults),
            ]];
        });

        return Inertia::render('Leasing/Deals/Quotes/WizardStep3', [
            'deal'   => $deal,
            'drafts' => $drafts,
            'sheets' => $sheets,
        ]);
    }

    /** Live recompute endpoint — front-end calls on every input change */
    public function step3Compute(Request $r, Deal $deal, DealQuote $quote, \App\Services\LeaseWorksheet $svc): JsonResponse
    {
        abort_unless($quote->deal_id === $deal->id, 404);
        return response()->json($svc->compute($quote, $r->input('worksheet', [])));
    }

    /** Step 3 save — finalize each draft with the computed numbers. */
    public function step3Save(Request $r, Deal $deal, \App\Services\LeaseWorksheet $svc)
    {
        $payload = $r->validate([
            'sheets'                 => 'required|array',
            'sheets.*.draft_id'      => 'required|exists:deal_quotes,id',
            'sheets.*.inputs'        => 'required|array',
        ]);

        foreach ($payload['sheets'] as $row) {
            $quote = DealQuote::where('id', $row['draft_id'])->where('deal_id', $deal->id)->first();
            if (!$quote) continue;

            $result = $svc->compute($quote, $row['inputs']);
            $acqFee = collect($row['inputs']['fees'] ?? [])->firstWhere('name', 'Acquisition Fee');

            $quote->update([
                'monthly_payment'      => $result['total_monthly'],
                'das'                  => $result['due_at_signing'],
                'sell_price'           => $result['sell_price'],
                'msrp'                 => $result['msrp'],
                'rebates'              => $row['inputs']['rebates'] ?? $quote->rebates,
                'acquisition_fee'      => $acqFee['amount'] ?? $quote->acquisition_fee,
                'acquisition_fee_type' => $acqFee['paid_as'] ?? $quote->acquisition_fee_type,
                'residual_value'       => $result['residual_value'],
                'money_factor'         => $result['sell_money_factor'],
                'tax_breakdown'        => [
                    'taxes_paid_as' => $result['taxes_paid_as'],
                    'tax_rate'      => $result['tax_rate'],
                    'monthly_tax'   => $result['monthly_tax'],
                    'upfront_tax'   => $result['upfront_tax'],
                    'profit'        => $result['profit'],
                    'fees'          => $row['inputs']['fees'] ?? [],
                ],
                'is_draft'             => false,
            ]);
        }

        return redirect()->route('leasing.deals.show', $deal)
            ->with('success', count($payload['sheets']) . ' quote(s) finalized.');
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
            'applied_rebates'               => 'nullable|array',  // full rebate objects {id,title,cashback,source,...}
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
        return redirect()->route('leasing.deals.quotes.wizard.step2', $deal)
            ->with('success', count($createdIds) === 1
                ? "Quote drafted. Pick a lender →"
                : count($createdIds) . " draft quotes created (one per term). Pick a lender for each →");
    }
}
