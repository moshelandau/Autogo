<?php

namespace App\Http\Controllers;

use App\Services\QuoteCalculatorService;
use Illuminate\Http\Request;

class QuoteCalculatorController extends Controller
{
    public function __construct(private readonly QuoteCalculatorService $calc) {}

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:lease,finance',
            'msrp' => 'nullable|numeric',
            'sell_price' => 'required|numeric',
            'term' => 'required|integer',
            'annual_mileage' => 'nullable|integer',
            'residual_pct' => 'nullable|numeric',
            'money_factor' => 'nullable|numeric',
            'apr' => 'nullable|numeric',
            'acquisition_fee' => 'nullable|numeric',
            'down_payment' => 'nullable|numeric',
            'trade_equity' => 'nullable|numeric',
            'rebates_total' => 'nullable|numeric',
            'tax_rate' => 'nullable|numeric',
            'doc_fees' => 'nullable|numeric',
        ]);

        $result = $validated['type'] === 'lease'
            ? $this->calc->calculateLease($validated)
            : $this->calc->calculateFinance($validated);

        return response()->json($result);
    }

    public function findProgram(Request $request)
    {
        $validated = $request->validate([
            'make' => 'required|string',
            'model' => 'required|string',
            'term' => 'required|integer',
            'annual_mileage' => 'required|integer',
            'credit_score' => 'nullable|integer',
        ]);

        $program = $this->calc->findBestProgram(
            $validated['make'],
            $validated['model'],
            $validated['term'],
            $validated['annual_mileage'],
            $validated['credit_score'] ?? null,
        );

        return response()->json(['program' => $program?->load('lender')]);
    }

    public function rebates(Request $request)
    {
        $validated = $request->validate([
            'make' => 'required|string',
            'model' => 'nullable|string',
            'year' => 'nullable|integer',
        ]);

        return response()->json([
            'rebates' => $this->calc->getEligibleRebates(
                $validated['make'],
                $validated['model'] ?? '',
                $validated['year'] ?? null,
            ),
        ]);
    }
}
