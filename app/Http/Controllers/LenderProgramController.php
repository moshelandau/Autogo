<?php

namespace App\Http\Controllers;

use App\Models\Lender;
use App\Models\LenderProgram;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LenderProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = LenderProgram::with(['lender'])
            ->when($request->lender_id, fn($q, $id) => $q->where('lender_id', $id))
            ->when($request->make, fn($q, $m) => $q->where('make', $m))
            ->when($request->active === 'true', fn($q) => $q->active())
            ->orderByDesc('valid_from')
            ->paginate(50);

        return Inertia::render('LenderPrograms/Index', [
            'programs' => $programs,
            'lenders' => Lender::active()->get(),
            'filters' => $request->only(['lender_id', 'make', 'active']),
            'stats' => [
                'total' => LenderProgram::count(),
                'active' => LenderProgram::active()->count(),
                'expired' => LenderProgram::where('valid_until', '<', today())->count(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('LenderPrograms/Create', [
            'lenders' => Lender::active()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lender_id' => 'required|exists:lenders,id',
            'program_type' => 'required|in:lease,finance',
            'make' => 'nullable|string',
            'model' => 'nullable|string',
            'year' => 'nullable|integer',
            'term' => 'nullable|integer',
            'annual_mileage' => 'nullable|integer',
            'residual_pct' => 'nullable|numeric',
            'money_factor' => 'nullable|numeric',
            'apr' => 'nullable|numeric',
            'acquisition_fee' => 'nullable|numeric',
            'min_credit_score' => 'nullable|integer',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'notes' => 'nullable|string',
        ]);

        LenderProgram::create(array_merge($validated, [
            'created_by' => auth()->id(),
            'is_active' => true,
            'source' => 'manual',
        ]));

        return redirect()->route('lender-programs.index')->with('success', 'Lender program created.');
    }

    public function update(Request $request, LenderProgram $lenderProgram)
    {
        $validated = $request->validate([
            'residual_pct' => 'nullable|numeric',
            'money_factor' => 'nullable|numeric',
            'apr' => 'nullable|numeric',
            'is_active' => 'boolean',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $lenderProgram->update($validated);

        return back()->with('success', 'Program updated.');
    }
}
