<?php

namespace App\Http\Controllers;

use App\Models\Lender;
use App\Services\LeasingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LenderController extends Controller
{
    public function __construct(private readonly LeasingService $leasing) {}

    public function index()
    {
        return Inertia::render('Leasing/Lenders/Index', [
            'lenders' => Lender::withCount('deals')->orderBy('name')->paginate(50),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'programs_notes' => 'nullable|string',
        ]);

        $this->leasing->createLender($validated);

        return back()->with('success', 'Lender added.');
    }

    public function update(Request $request, Lender $lender)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'programs_notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $this->leasing->updateLender($lender, $validated);

        return back()->with('success', 'Lender updated.');
    }
}
