<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LenderProgram;
use App\Models\ManufacturerRebate;

class QuoteCalculatorService
{
    /**
     * Calculate a lease quote.
     *
     * @param array $input { msrp, sell_price, term, annual_mileage,
     *                      residual_pct, money_factor, acquisition_fee,
     *                      down_payment, trade_equity, rebates_total,
     *                      tax_rate, doc_fees }
     */
    public function calculateLease(array $input): array
    {
        $msrp = (float) ($input['msrp'] ?? 0);
        $sellPrice = (float) ($input['sell_price'] ?? $msrp);
        $term = (int) ($input['term'] ?? 36);
        $residualPct = (float) ($input['residual_pct'] ?? 0);
        $moneyFactor = (float) ($input['money_factor'] ?? 0);
        $acquisitionFee = (float) ($input['acquisition_fee'] ?? 0);
        $downPayment = (float) ($input['down_payment'] ?? 0);
        $tradeEquity = (float) ($input['trade_equity'] ?? 0);
        $rebatesTotal = (float) ($input['rebates_total'] ?? 0);
        $taxRate = (float) ($input['tax_rate'] ?? 0); // e.g. 0.08875
        $docFees = (float) ($input['doc_fees'] ?? 0);

        // Residual value
        $residualValue = round($msrp * ($residualPct / 100), 2);

        // Capitalized cost (sell price - cap reduction)
        $capReduction = $downPayment + $tradeEquity + $rebatesTotal;
        $adjustedCapCost = $sellPrice + $acquisitionFee - $capReduction;

        // Depreciation portion
        $depreciation = ($adjustedCapCost - $residualValue) / $term;

        // Rent charge (interest)
        $rentCharge = ($adjustedCapCost + $residualValue) * $moneyFactor;

        // Pre-tax base monthly
        $baseMonthly = $depreciation + $rentCharge;

        // Tax (NY uses pay-as-you-go: tax on monthly payment)
        $monthlyTax = $baseMonthly * $taxRate;

        // Total monthly payment
        $monthlyPayment = round($baseMonthly + $monthlyTax, 2);

        // DAS (drive at signing) — first month + cap reduction + fees + tax on cap reduction
        $das = round($monthlyPayment + $capReduction + $docFees + ($capReduction * $taxRate), 2);

        // APR equivalent of money factor
        $apr = round($moneyFactor * 2400, 2);

        return [
            'monthly_payment' => $monthlyPayment,
            'das' => $das,
            'depreciation' => round($depreciation, 2),
            'rent_charge' => round($rentCharge, 2),
            'monthly_tax' => round($monthlyTax, 2),
            'residual_value' => $residualValue,
            'cap_reduction' => round($capReduction, 2),
            'adjusted_cap_cost' => round($adjustedCapCost, 2),
            'apr_equivalent' => $apr,
            'total_lease_cost' => round($monthlyPayment * $term + $das - $monthlyPayment, 2),
            'inputs' => $input,
        ];
    }

    /**
     * Calculate a finance quote (standard amortization).
     */
    public function calculateFinance(array $input): array
    {
        $sellPrice = (float) ($input['sell_price'] ?? 0);
        $apr = (float) ($input['apr'] ?? 0); // e.g. 5.99
        $term = (int) ($input['term'] ?? 60);
        $downPayment = (float) ($input['down_payment'] ?? 0);
        $tradeEquity = (float) ($input['trade_equity'] ?? 0);
        $rebatesTotal = (float) ($input['rebates_total'] ?? 0);
        $taxRate = (float) ($input['tax_rate'] ?? 0);
        $docFees = (float) ($input['doc_fees'] ?? 0);

        // Tax on full price (NY does this for finance)
        $taxAmount = $sellPrice * $taxRate;

        // Amount financed
        $amountFinanced = $sellPrice + $taxAmount + $docFees - $downPayment - $tradeEquity - $rebatesTotal;

        // Monthly payment using amortization formula
        $monthlyRate = ($apr / 100) / 12;
        if ($monthlyRate > 0) {
            $monthlyPayment = $amountFinanced * ($monthlyRate * pow(1 + $monthlyRate, $term)) / (pow(1 + $monthlyRate, $term) - 1);
        } else {
            $monthlyPayment = $amountFinanced / $term;
        }

        return [
            'monthly_payment' => round($monthlyPayment, 2),
            'amount_financed' => round($amountFinanced, 2),
            'tax_amount' => round($taxAmount, 2),
            'down_payment' => $downPayment,
            'total_interest' => round(($monthlyPayment * $term) - $amountFinanced, 2),
            'total_paid' => round($monthlyPayment * $term + $downPayment, 2),
            'inputs' => $input,
        ];
    }

    /**
     * Find best lender program for a vehicle.
     */
    public function findBestProgram(string $make, string $model, int $term, int $annualMileage, ?int $creditScore = null): ?LenderProgram
    {
        return LenderProgram::active()
            ->forVehicle($make, $model)
            ->where('term', $term)
            ->where('annual_mileage', $annualMileage)
            ->when($creditScore, fn($q) => $q->where(fn($q2) => $q2->whereNull('min_credit_score')->orWhere('min_credit_score', '<=', $creditScore)))
            ->orderByDesc('residual_pct')
            ->orderBy('money_factor')
            ->first();
    }

    /**
     * Get all eligible rebates for a vehicle.
     */
    public function getEligibleRebates(string $make, string $model, ?int $year = null): array
    {
        return ManufacturerRebate::active()
            ->where(function ($q) use ($make) {
                $q->where('make', $make)->orWhereNull('make');
            })
            ->where(function ($q) use ($model) {
                $q->where('model', $model)->orWhereNull('model');
            })
            ->orderByDesc('amount')
            ->get()
            ->toArray();
    }
}
