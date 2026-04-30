<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\VehicleReturn;
use Illuminate\Http\Request;

class VehicleReturnController extends Controller
{
    /**
     * Upsert: deals only ever have ONE vehicle return today (matches xDeskPro).
     */
    public function store(Request $request, Deal $deal)
    {
        $data = $this->validated($request);
        $deal->vehicleReturn()->updateOrCreate(['deal_id' => $deal->id], $data);
        return back()->with('success', 'Vehicle return saved.');
    }

    public function destroy(Deal $deal, VehicleReturn $vehicleReturn)
    {
        abort_unless($vehicleReturn->deal_id === $deal->id, 404);
        $vehicleReturn->delete();
        return back()->with('success', 'Vehicle return removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'return_type'         => 'required|in:trade_in,lease_return',
            'vin'                 => 'nullable|string|max:17',
            'year'                => 'nullable|integer|min:1900|max:2099',
            'make'                => 'nullable|string|max:64',
            'model'               => 'nullable|string|max:64',
            'trim'                => 'nullable|string|max:64',
            'color'               => 'nullable|string|max:32',
            'odometer'            => 'nullable|integer|min:0',
            'condition'           => 'nullable|in:excellent,good,fair,poor',
            'payoff_amount'       => 'nullable|numeric|min:0',
            'allowance'           => 'nullable|numeric|min:0',
            'acv'                 => 'nullable|numeric|min:0',
            'payoff_to'           => 'nullable|string|max:255',
            'payoff_good_through' => 'nullable|date',
            'current_plate'       => 'nullable|string|max:20',
            'plate_transfer'      => 'boolean',
            'notes'               => 'nullable|string',
        ]);
    }
}
