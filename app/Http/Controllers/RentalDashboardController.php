<?php

namespace App\Http\Controllers;

use App\Services\RentalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RentalDashboardController extends Controller
{
    public function __construct(private readonly RentalService $rental) {}

    public function __invoke(Request $request)
    {
        return Inertia::render('Rental/Dashboard', [
            'manifest' => $this->rental->getDailyManifest($request->date),
            'fleet' => $this->rental->getFleetUtilization(),
        ]);
    }
}
