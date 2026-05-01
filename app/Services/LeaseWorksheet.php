<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DealQuote;

/**
 * Lease worksheet math — pure functions, no DB writes.
 *
 * Inputs in $worksheet (all optional, sensible defaults):
 *   fees:                [{name, amount, paid_as: 'upfront'|'capped'}, …]
 *   taxes_paid_as:       'upfront'|'capped'
 *   tax_rate:            decimal, e.g. 0.08125 for 8.125%
 *   buy_money_factor:    decimal — from lender program (the BUY rate)
 *   sell_money_factor:   decimal — what we charge customer (SELL rate)
 *                         the spread × term × balance = reserve profit.
 *   base_residual_pct:   from program (e.g. 60.0)
 *   adj_residual_pct:    optional bump (e.g. +5.0)
 *   max_advance_pct:     lender LTV cap (e.g. 115.0)
 *   cap_cost_reduction:  customer down payment that reduces cap (vs.
 *                         "drive off" which is just upfront fees)
 *   rebates:             total $ of applied rebates
 *   trade_allowance:     credit toward cap reduction
 *   trade_payoff:        rolled INTO cap (negative equity)
 *   trade_acv:           actual cash value (for trade profit calc)
 *   cost:                dealer cost of vehicle (for vehicle profit)
 */
class LeaseWorksheet
{
    public function compute(DealQuote $quote, array $w = []): array
    {
        // ── Inputs with defaults ──
        $msrp        = (float) ($quote->msrp ?? 0);
        $term        = (int)   ($quote->term ?: 36);
        $cost        = (float) ($w['cost'] ?? 0);

        // Profit target: when set, sell_price is back-solved as cost +
        // target_profit. Otherwise sell_price flows through from the input.
        $profitTarget = isset($w['vehicle_profit_target']) ? (float) $w['vehicle_profit_target'] : null;
        $sellPrice = $profitTarget !== null
            ? round($cost + $profitTarget, 2)
            : (float) ($w['sell_price'] ?? $quote->sell_price ?? 0);

        $buyMf       = (float) ($w['buy_money_factor']  ?? $quote->money_factor ?? 0);
        $sellMf      = (float) ($w['sell_money_factor'] ?? $buyMf);
        $baseResPct  = (float) ($w['base_residual_pct'] ?? ($quote->msrp ? ($quote->residual_value / $quote->msrp * 100) : 0));
        $adjResPct   = (float) ($w['adj_residual_pct']  ?? 0);
        $totResPct   = $baseResPct + $adjResPct;
        $maxAdvPct   = (float) ($w['max_advance_pct']   ?? 115);

        $taxRate     = (float) ($w['tax_rate'] ?? 0.08125);
        $taxesPaidAs = $w['taxes_paid_as'] ?? 'capped';

        $rebates     = (float) ($w['rebates'] ?? $quote->rebates ?? 0);
        $tradeAllow  = (float) ($w['trade_allowance'] ?? 0);
        $tradePayoff = (float) ($w['trade_payoff']    ?? 0);
        $tradeAcv    = (float) ($w['trade_acv']       ?? 0);
        $capRed      = (float) ($w['cap_cost_reduction'] ?? 0);

        // ── Fees: split into upfront vs capped buckets ──
        $fees = $w['fees'] ?? [];
        $upfrontFees = 0;
        $cappedFees  = 0;
        foreach ($fees as $f) {
            $amt = (float) ($f['amount'] ?? 0);
            if (($f['paid_as'] ?? 'capped') === 'upfront') $upfrontFees += $amt;
            else                                            $cappedFees  += $amt;
        }

        // ── Residual value in dollars ──
        $residual = round($msrp * $totResPct / 100, 2);

        // ── Capitalized cost ──
        // gross = sell + capped fees (taxes added below if capped)
        // net   = gross - rebates - trade_allowance + trade_payoff - cap_cost_reduction
        // (trade_payoff with no allowance = full negative equity rolled into cap)
        $tradeCredit = max(0, $tradeAllow - $tradePayoff); // positive credit
        $negEquity   = max(0, $tradePayoff - $tradeAllow); // rolled into cap

        $grossCapBeforeTax = $sellPrice + $cappedFees;

        // Sales tax on a lease = tax × (depreciation + rent_charge) per month in some states,
        // OR tax × monthly payment in others. NY (and most northeast) tax the monthly payment.
        // We compute monthly first WITHOUT tax, then layer monthly tax onto it. Capped taxes
        // ARE added to cap_cost in some states (NJ); we treat 'capped' as "tax included in
        // each monthly payment but rolled into cap math" — close enough for the v1 worksheet.
        $netCap = $grossCapBeforeTax - $rebates - $tradeCredit + $negEquity - $capRed;

        // ── Lease math ──
        $depreciation = $term > 0 ? round(($netCap - $residual) / $term, 2) : 0;
        $rentCharge   = round(($netCap + $residual) * $sellMf, 2);
        $baseMonthly  = round($depreciation + $rentCharge, 2);

        $monthlyTax = $taxesPaidAs === 'upfront' ? 0 : round($baseMonthly * $taxRate, 2);
        $totalMonthly = round($baseMonthly + $monthlyTax, 2);

        // Upfront tax payment if elected
        $upfrontTaxTotal = $taxesPaidAs === 'upfront'
            ? round($baseMonthly * $taxRate * $term, 2)
            : 0;

        // ── Due at signing = upfront fees + 1st month + cap cost reduction + upfront tax ──
        $dueAtSigning = round($upfrontFees + $totalMonthly + $capRed + $upfrontTaxTotal, 2);

        // ── Profit breakdown ──
        // Reserve profit ≈ (sell_mf - buy_mf) × (cap + residual) × term × 0.85 (dealer split factor)
        // 0.85 is a typical AutoGo cut after lender takes their share. Marked as approximate.
        $reserveSpread = max(0, $sellMf - $buyMf);
        $reserveProfit = round($reserveSpread * ($netCap + $residual) * $term * 0.85, 2);

        $vehicleProfit = round($sellPrice - $cost, 2);
        $tradeProfit   = round(max(0, $tradeAcv - $tradeAllow), 2);

        $totalProfit = round($vehicleProfit + $tradeProfit + $reserveProfit, 2);

        // ── Max advance check (lender LTV cap) ──
        $maxAdvanceAmount = round($msrp * $maxAdvPct / 100, 2);
        $withinMaxAdvance = $netCap <= $maxAdvanceAmount;
        $maxAdvanceOver   = $withinMaxAdvance ? 0 : round($netCap - $maxAdvanceAmount, 2);

        return [
            // Inputs echoed back for the UI to render the table
            'msrp'             => $msrp,
            'sell_price'       => $sellPrice,
            'term'             => $term,
            'buy_money_factor' => $buyMf,
            'sell_money_factor'=> $sellMf,
            'reserve_spread'   => $reserveSpread,
            'base_residual_pct'=> $baseResPct,
            'adj_residual_pct' => $adjResPct,
            'total_residual_pct' => $totResPct,
            'tax_rate'         => $taxRate,
            'taxes_paid_as'    => $taxesPaidAs,

            // Cap cost
            'gross_cap_cost'   => round($grossCapBeforeTax, 2),
            'net_cap_cost'     => round($netCap, 2),
            'residual_value'   => $residual,

            // Fees
            'upfront_fees'     => round($upfrontFees, 2),
            'capped_fees'      => round($cappedFees, 2),
            'upfront_tax'      => $upfrontTaxTotal,

            // Trade
            'trade_credit'     => round($tradeCredit, 2),
            'negative_equity'  => round($negEquity, 2),

            // Monthly
            'depreciation'     => $depreciation,
            'rent_charge'      => $rentCharge,
            'base_monthly'     => $baseMonthly,
            'monthly_tax'      => $monthlyTax,
            'total_monthly'    => $totalMonthly,

            // Due at signing
            'due_at_signing'   => $dueAtSigning,

            // Profit
            'profit' => [
                'vehicle' => $vehicleProfit,
                'trade'   => $tradeProfit,
                'reserve' => $reserveProfit,
                'total'   => $totalProfit,
            ],

            // Max advance
            'max_advance_pct'    => $maxAdvPct,
            'max_advance_amount' => $maxAdvanceAmount,
            'within_max_advance' => $withinMaxAdvance,
            'max_advance_over'   => $maxAdvanceOver,

            // APR equivalent of the SELL money factor (for staff sanity)
            'apr_equivalent'   => round($sellMf * 2400, 3),
        ];
    }
}
