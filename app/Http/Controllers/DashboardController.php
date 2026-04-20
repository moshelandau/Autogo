<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(AccountingService $accounting)
    {
        $departmentSummary = $accounting->getDepartmentSummary(
            now()->startOfMonth()->toDateString(),
            now()->toDateString()
        );

        return Inertia::render('Dashboard', [
            'departmentSummary' => $departmentSummary,
            'stats' => [
                'total_customers' => \App\Models\Customer::count(),
                'active_locations' => \App\Models\Location::where('is_active', true)->count(),
            ],
        ]);
    }
}
