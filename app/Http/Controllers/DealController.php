<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Customer;
use App\Models\Lender;
use App\Services\LeasingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DealController extends Controller
{
    public function __construct(private readonly LeasingService $leasing) {}

    public function index(Request $request)
    {
        if ($request->view === 'list') {
            return Inertia::render('Leasing/Deals/Index', [
                'deals' => $this->leasing->getDeals($request->stage, $request->search),
                'filters' => $request->only(['search', 'stage', 'view']),
            ]);
        }

        return Inertia::render('Leasing/Deals/Kanban', [
            'stages' => $this->leasing->getDealsByStage(),
            'stats' => $this->leasing->getDashboardStats(),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Leasing/Deals/Create', [
            'customers'   => Customer::where('is_active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'phone', 'credit_score']),
            'lenders'     => Lender::active()->get(),
            'salespeople' => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'prefill'     => [ 'customer_id' => $request->integer('customer_id') ?: null ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_type' => 'required|in:lease,finance,one_pay,balloon,cash',
            'priority' => 'nullable|in:low,medium,high',
            'stage' => 'nullable|in:' . implode(',', array_diff(\App\Models\Deal::STAGES, ['lost'])),
            'preferences' => 'nullable|array',
            'preferences.style' => 'nullable|string|max:60',
            'preferences.budget' => 'nullable|numeric|min:0',
            'preferences.miles_per_year' => 'nullable|integer|min:0',
            'preferences.passengers' => 'nullable|integer|min:1|max:12',
            'preferences.color' => 'nullable|string|max:40',
            'preferences.brand' => 'nullable|string|max:60',
            'vehicle_vin' => 'nullable|string|max:17',
            'vehicle_year' => 'nullable|integer',
            'vehicle_make' => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_trim' => 'nullable|string|max:100',
            'msrp' => 'nullable|numeric|min:0',
            'sell_price' => 'nullable|numeric|min:0',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'customer_zip' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
        ]);

        $deal = $this->leasing->createDeal($validated);

        return redirect()->route('leasing.deals.show', $deal)
            ->with('success', "Deal #{$deal->deal_number} created.");
    }

    /**
     * Ensures the new STAGE_TASKS template is reflected on legacy deals
     * across every active stage — firstOrCreate is idempotent so existing
     * tasks (incl. completion state) are untouched, missing template
     * tasks are added. The Tasks tab defaults to showing the whole
     * workflow, so we need every stage's tasks present.
     */
    private function syncCurrentStageTasks(Deal $deal): void
    {
        foreach (Deal::STAGES as $stage) {
            if ($stage === 'lost') continue;
            $deal->generateTasksForStage($stage);
        }
    }

    public function show(Deal $deal)
    {
        $this->syncCurrentStageTasks($deal);
        $deal->load(['customer', 'coSigner', 'salesperson', 'lender', 'quotes.lender', 'tasks', 'documents', 'dealNotes.user']);

        // Credit-pull history for this deal's customer (most-recent first)
        $creditPulls = $deal->customer
            ? \App\Models\CreditPull::where('customer_id', $deal->customer_id)
                ->with('pulledByUser:id,name')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
            : collect();

        return Inertia::render('Leasing/Deals/Show', [
            'deal' => $deal,
            'lenders' => Lender::active()->get(),
            'inspectionComparison' => null,
            'creditPulls' => $creditPulls,
            'creditConfigured' => !empty(config('services.credit700.api_key')),
        ]);
    }

    /**
     * Run a soft credit pull inline from the deal's Credit tab.
     * Saves the pull, attaches it to this deal + customer, refreshes the score on the deal.
     */
    public function pullCredit(Request $request, Deal $deal, \App\Services\Credit700Service $credit)
    {
        if (!$deal->customer) abort(422, 'Deal has no customer');

        $c = $deal->customer;
        $pull = \App\Models\CreditPull::create([
            'customer_id'        => $c->id,
            'deal_id'            => $deal->id,
            'type'               => 'soft',
            'first_name'         => $c->first_name,
            'last_name'          => $c->last_name,
            'date_of_birth'      => $c->date_of_birth,
            'address'            => $c->address,
            'city'               => $c->city,
            'state'              => $c->state,
            'zip'                => $c->zip,
            'pulled_by'          => auth()->id(),
            'permissible_purpose'=> 'prequalification',
            'ip_address'         => $request->ip(),
            'status'             => 'pending',
        ]);

        $result = $credit->softPull($pull);

        if (!($result['success'] ?? false)) {
            return back()->with('error', 'Credit pull failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        // Cache the score on the deal for quick display
        $deal->update(['credit_score' => $result['score']]);

        $msg = "Soft pull complete — Score: {$result['score']}";
        if ($result['mock'] ?? false) $msg .= ' (mock — set CREDIT700_API_KEY for real pulls)';
        return back()->with('success', $msg);
    }

    public function update(Request $request, Deal $deal)
    {
        $validated = $request->validate([
            'vehicle_vin' => 'nullable|string|max:17',
            'vehicle_year' => 'nullable|integer',
            'vehicle_make' => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_trim' => 'nullable|string|max:100',
            'vehicle_color' => 'nullable|string|max:50',
            'payment_type' => 'nullable|in:lease,finance,one_pay,balloon,cash',
            'priority' => 'nullable|in:low,medium,high',
            'msrp' => 'nullable|numeric|min:0',
            'invoice_price' => 'nullable|numeric|min:0',
            'sell_price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'profit' => 'nullable|numeric',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'customer_zip' => 'nullable|string|max:10',
            'trade_allowance' => 'nullable|numeric',
            'trade_acv' => 'nullable|numeric',
            'trade_payoff' => 'nullable|numeric',
            'notes' => 'nullable|string',
            // Workflow structured fields
            'preferences' => 'nullable|array',
            'preferences.style' => 'nullable|string|max:60',
            'preferences.budget' => 'nullable|numeric|min:0',
            'preferences.miles_per_year' => 'nullable|integer|min:0',
            'preferences.passengers' => 'nullable|integer|min:1|max:12',
            'preferences.color' => 'nullable|string|max:40',
            'preferences.brand' => 'nullable|string|max:60',
            'co_signer_customer_id' => 'nullable|exists:customers,id',
            'insurance_status' => 'nullable|string|in:pending,verified,needs_update,n/a',
            'plate_transfer' => 'boolean',
            'delivery_scheduled_at' => 'nullable|date',
            'down_collected_at_delivery' => 'nullable|numeric|min:0',
            'paperwork_tracking_number' => 'nullable|string|max:60',
            'bd_payment_received_at' => 'nullable|date',
            'bd_payment_amount' => 'nullable|numeric|min:0',
        ]);

        $this->leasing->updateDeal($deal, $validated);

        return back()->with('success', 'Deal updated.');
    }

    public function transition(Request $request, Deal $deal)
    {
        $validated = $request->validate([
            'stage' => 'required|in:' . implode(',', Deal::STAGES),
        ]);

        $this->leasing->transitionDeal($deal, $validated['stage']);

        return back()->with('success', "Deal moved to {$validated['stage']}.");
    }

    public function reorder(Request $request, Deal $deal)
    {
        $validated = $request->validate([
            'stage'     => 'required|in:' . implode(',', Deal::STAGES),
            'before_id' => 'nullable|integer|exists:deals,id',
        ]);

        $this->leasing->reorderDeal($deal, $validated['stage'], $validated['before_id'] ?? null);

        return back();
    }

    public function markLost(Request $request, Deal $deal)
    {
        $this->leasing->markDealLost($deal, $request->reason);
        return back()->with('success', 'Deal marked as lost.');
    }

    public function completeTask(Request $request, Deal $deal, int $taskId)
    {
        $task = $deal->tasks()->findOrFail($taskId);
        $this->leasing->completeTask($task);
        return back()->with('success', 'Task completed.');
    }

    public function addNote(Request $request, Deal $deal)
    {
        $validated = $request->validate(['body' => 'required|string']);
        $deal->dealNotes()->create(['body' => $validated['body'], 'user_id' => auth()->id()]);
        return back()->with('success', 'Note added.');
    }

    public function addQuote(Request $request, Deal $deal)
    {
        $validated = $request->validate([
            'lender_id' => 'nullable|exists:lenders,id',
            'payment_type' => 'required|in:lease,finance,one_pay,balloon,cash',
            'term' => 'nullable|integer',
            'mileage_per_year' => 'nullable|integer',
            'monthly_payment' => 'nullable|numeric',
            'das' => 'nullable|numeric',
            'sell_price' => 'nullable|numeric',
            'msrp' => 'nullable|numeric',
            'rebates' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $this->leasing->createQuote($deal, $validated);

        return back()->with('success', 'Quote added.');
    }

    public function selectQuote(Deal $deal, int $quoteId)
    {
        $quote = $deal->quotes()->findOrFail($quoteId);
        $this->leasing->selectQuote($quote);
        return back()->with('success', 'Quote selected.');
    }

    public function decodeVin(Request $request)
    {
        $request->validate(['vin' => 'required|string|size:17']);
        $result = $this->leasing->decodeVin($request->vin);
        return response()->json($result);
    }
}
