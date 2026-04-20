<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\ClaimStep;
use App\Models\Customer;
use App\Services\ClaimService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClaimController extends Controller
{
    public function __construct(private readonly ClaimService $claims) {}

    public function index(Request $request)
    {
        return Inertia::render('Claims/Index', [
            'claims' => $this->claims->getClaims($request->status, $request->search),
            'stats' => $this->claims->getDashboardStats(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Kanban board: claims grouped by their CURRENT step (first incomplete step).
     * Columns mirror the 9 canonical steps in Claim::STEP_NAMES.
     */
    public function board()
    {
        $stepNames = \App\Models\Claim::STEP_NAMES;

        $claims = \App\Models\Claim::with([
                'customer:id,first_name,last_name,phone',
                'insuranceEntries:id,claim_id,insurance_company,claim_number',
                'steps:id,claim_id,name,sort_order,is_completed',
            ])
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('created_at')
            ->get();

        // Determine each claim's current step index (0..8) or 'done' if all done
        $claims->each(function ($claim) use ($stepNames) {
            $totalSteps = count($stepNames);
            if ($claim->steps->isEmpty()) {
                // No steps yet → sit in column 0
                $claim->current_step_index = 0;
                $claim->completed_count = 0;
            } else {
                $stepsSorted = $claim->steps->sortBy('sort_order')->values();
                $firstOpen = $stepsSorted->firstWhere('is_completed', false);
                $completed = $stepsSorted->where('is_completed', true)->count();
                $claim->completed_count = $completed;
                $claim->current_step_index = $firstOpen
                    ? (int) $firstOpen->sort_order
                    : $totalSteps; // all done
            }
        });

        $columns = collect($stepNames)->map(function ($name, $i) use ($claims) {
            $cards = $claims->filter(fn ($c) => $c->current_step_index === $i)->values()->all();
            return [
                'id'    => (string) $i,
                'label' => $name,
                'step'  => $i + 1,
                'cards' => $cards,
                'count' => count($cards),
                'total' => round((float) collect($cards)->sum('estimate_amount'), 2),
            ];
        })->all();

        // "Done" column for claims with all 9 steps complete
        $doneCards = $claims->filter(fn ($c) => $c->current_step_index === count($stepNames))->values()->all();
        $columns[] = [
            'id'    => 'done',
            'label' => '✓ Done',
            'step'  => count($stepNames) + 1,
            'cards' => $doneCards,
            'count' => count($doneCards),
            'total' => round((float) collect($doneCards)->sum('estimate_amount'), 2),
        ];

        return Inertia::render('Claims/Board', [
            'columns' => $columns,
            'stats'   => $this->claims->getDashboardStats(),
        ]);
    }

    /**
     * Move a claim to a specific step column (drag & drop).
     * Marks all prior steps as completed, and the target step + all later steps as incomplete.
     */
    public function setStep(Request $request, \App\Models\Claim $claim)
    {
        $data = $request->validate([
            'step_index' => 'required|integer|min:0|max:9', // 9 = done column
        ]);
        $targetIndex = $data['step_index'];

        // Ensure steps exist
        if ($claim->steps()->count() === 0) {
            $claim->generateSteps();
            $claim->load('steps');
        }

        foreach ($claim->steps()->orderBy('sort_order')->get() as $step) {
            $shouldBeComplete = $step->sort_order < $targetIndex;
            if ($step->is_completed !== $shouldBeComplete) {
                $step->update([
                    'is_completed' => $shouldBeComplete,
                    'completed_at' => $shouldBeComplete ? now() : null,
                    'completed_by' => $shouldBeComplete ? auth()->id() : null,
                ]);
            }
        }

        // Sync overall status based on position
        $claim->update([
            'status' => match (true) {
                $targetIndex === 0                       => 'new',
                $targetIndex >= count(\App\Models\Claim::STEP_NAMES) => 'completed',
                default                                   => 'in_progress',
            },
        ]);

        return back()->with('success', 'Step updated.');
    }

    /** Legacy: alias kept so old board calls still work. */
    public function setStatus(Request $request, \App\Models\Claim $claim)
    {
        $data = $request->validate([
            'status' => 'required|in:new,filed,in_progress,completed',
        ]);
        $claim->update($data);
        return back()->with('success', 'Status updated.');
    }

    public function create()
    {
        return Inertia::render('Claims/Create', [
            'customers' => Customer::where('is_active', true)->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'phone', 'insurance_company', 'insurance_policy']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'story' => 'nullable|string',
            'accident_date' => 'nullable|date',
            'accident_location' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'vehicle_year' => 'nullable|string|max:4',
            'vehicle_make' => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_vin' => 'nullable|string|max:17',
            'vehicle_plate' => 'nullable|string|max:20',
            'estimate_amount' => 'nullable|numeric|min:0',
            'towing_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'insurance_entries' => 'nullable|array',
            'insurance_entries.*.insurance_company' => 'required|string|max:255',
            'insurance_entries.*.claim_number' => 'required|string|max:100',
        ]);

        $claim = $this->claims->createClaim($validated);

        return redirect()->route('claims.show', $claim)->with('success', 'Claim created with 9-step checklist.');
    }

    public function show(Claim $claim)
    {
        $claim->load(['customer', 'insuranceEntries', 'steps', 'supplements', 'documents', 'comments.user', 'createdByUser']);

        return Inertia::render('Claims/Show', ['claim' => $claim]);
    }

    public function update(Request $request, Claim $claim)
    {
        $validated = $request->validate([
            'story' => 'nullable|string',
            'accident_date' => 'nullable|date',
            'accident_location' => 'nullable|string|max:255',
            'adjuster_name' => 'nullable|string|max:255',
            'adjuster_phone' => 'nullable|string|max:20',
            'adjuster_email' => 'nullable|email',
            'appraiser_name' => 'nullable|string|max:255',
            'appraiser_phone' => 'nullable|string|max:20',
            'appraiser_email' => 'nullable|email',
            'estimate_amount' => 'nullable|numeric|min:0',
            'approved_amount' => 'nullable|numeric|min:0',
            'towing_amount' => 'nullable|numeric|min:0',
            'rental_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->claims->updateClaim($claim, $validated);

        return back()->with('success', 'Claim updated.');
    }

    public function completeStep(Claim $claim, int $stepId)
    {
        $step = $claim->steps()->findOrFail($stepId);
        $this->claims->completeStep($step);
        return back()->with('success', "Step '{$step->name}' completed.");
    }

    public function uncompleteStep(Claim $claim, int $stepId)
    {
        $step = $claim->steps()->findOrFail($stepId);
        $this->claims->uncompleteStep($step);
        return back();
    }

    public function addInsurance(Request $request, Claim $claim)
    {
        $validated = $request->validate([
            'insurance_company' => 'required|string|max:255',
            'claim_number' => 'required|string|max:100',
            'policy_number' => 'nullable|string|max:100',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email',
        ]);

        $this->claims->addInsuranceEntry($claim, $validated);

        return back()->with('success', 'Insurance entry added.');
    }

    public function addSupplement(Request $request, Claim $claim)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'requested_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $this->claims->addSupplement($claim, $validated);

        return back()->with('success', 'Supplement added.');
    }

    public function addComment(Request $request, Claim $claim)
    {
        $validated = $request->validate(['body' => 'required|string']);
        $this->claims->addComment($claim, $validated['body']);
        return back()->with('success', 'Comment added.');
    }

    public function recordPayment(Request $request, Claim $claim)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ]);

        $this->claims->recordPayment($claim, (float) $validated['amount'], $validated['description'] ?? '');

        return back()->with('success', 'Payment recorded.');
    }
}
