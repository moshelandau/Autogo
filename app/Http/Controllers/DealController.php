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
        $deal->load(['customer.documents.uploadedBy', 'coSigner.documents', 'salesperson', 'lender', 'broker', 'dealer', 'lienholder', 'vehicleReturn', 'sharedWith:id,name',
            'customer.deals' => fn ($q) => $q->select(['id', 'deal_number', 'customer_id', 'stage', 'vehicle_year', 'vehicle_make', 'vehicle_model', 'created_at'])->orderByDesc('created_at')->limit(20),
            'quotes.lender', 'tasks', 'actionItems.completedBy', 'documents', 'noteThread.user', 'noteThread.assignedUsers', 'noteThread.comments.user', 'noteThread.activities.user']);

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
            'timeline' => app(\App\Services\DealTimelineService::class)->build($deal),
            'orgUsers' => \App\Models\User::orderBy('name')->get(['id', 'name']),
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
            'broker_id' => 'nullable|exists:insurance_brokers,id',
            'dealer_id' => 'nullable|exists:dealers,id',
            'lienholder_id' => 'nullable|exists:lienholders,id',
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
        // Toggle: tap a checked task again to un-complete it. Clears
        // completed_at + completed_by so the audit trail is honest.
        if ($task->is_completed) {
            $task->markIncomplete();
            return back()->with('success', 'Task reopened.');
        }
        $this->leasing->completeTask($task);
        return back()->with('success', 'Task completed.');
    }

    /**
     * Update a task's due date AND cascade-bump any subsequent task
     * (in stage-then-sort-order) whose due_date is now in the past
     * relative to the new date. Tasks already due on or after the new
     * date are left alone — the cascade only pushes dates forward.
     */
    public function updateTaskDueDate(Request $request, Deal $deal, int $taskId)
    {
        $validated = $request->validate(['due_date' => 'required|date']);
        $newDate = \Illuminate\Support\Carbon::parse($validated['due_date'])->startOfDay();

        $task = $deal->tasks()->findOrFail($taskId);
        $task->update(['due_date' => $newDate]);

        $stageRank = array_flip(Deal::STAGES);
        $tasks = $deal->tasks()
            ->get()
            ->sortBy(fn ($t) => ($stageRank[$t->stage] ?? 999) * 1000 + (int) $t->sort_order)
            ->values();

        $idx = $tasks->search(fn ($t) => $t->id === $task->id);
        if ($idx === false) return back()->with('success', 'Due date updated.');

        $bumped = 0;
        foreach ($tasks->slice($idx + 1) as $t) {
            if ($t->due_date && $t->due_date->lt($newDate)) {
                $t->update(['due_date' => $newDate]);
                $bumped++;
            }
        }

        $msg = $bumped ? "Due date updated. {$bumped} later task(s) shifted to match." : 'Due date updated.';
        return back()->with('success', $msg);
    }

    public function addNote(Request $request, Deal $deal)
    {
        $validated = $request->validate(['body' => 'required|string']);
        $deal->noteThread()->create(['body' => $validated['body'], 'user_id' => auth()->id()]);
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
            // Optional VIN-driven vehicle details — when present, also applied
            // to the deal so the right car is on file alongside the quote.
            'vehicle_vin' => 'nullable|string|max:17',
            'vehicle_year' => 'nullable|integer',
            'vehicle_make' => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_trim' => 'nullable|string|max:100',
        ]);

        // Split out vehicle fields so the quote row only stores quote-shaped data.
        $vehicleKeys = ['vehicle_vin', 'vehicle_year', 'vehicle_make', 'vehicle_model', 'vehicle_trim'];
        $vehicleData = array_filter(array_intersect_key($validated, array_flip($vehicleKeys)), fn ($v) => $v !== null && $v !== '');
        $quoteData = array_diff_key($validated, array_flip($vehicleKeys));

        if (!empty($vehicleData)) {
            $deal->update($vehicleData);
        }
        $this->leasing->createQuote($deal, $quoteData);

        return back()->with('success', 'Quote added.');
    }

    public function selectQuote(Deal $deal, int $quoteId)
    {
        $quote = $deal->quotes()->findOrFail($quoteId);
        $this->leasing->selectQuote($quote);
        return back()->with('success', 'Quote selected.');
    }

    public function updateQuote(Request $request, Deal $deal, int $quoteId)
    {
        $quote = $deal->quotes()->findOrFail($quoteId);
        $validated = $request->validate([
            'lender_id'        => 'nullable|exists:lenders,id',
            'payment_type'     => 'required|in:lease,finance,one_pay,balloon,cash',
            'term'             => 'nullable|integer',
            'mileage_per_year' => 'nullable|integer',
            'monthly_payment'  => 'nullable|numeric',
            'das'              => 'nullable|numeric',
            'sell_price'       => 'nullable|numeric',
            'msrp'             => 'nullable|numeric',
            'rebates'          => 'nullable|numeric',
            'notes'            => 'nullable|string',
        ]);
        $quote->update($validated);
        return back()->with('success', 'Quote updated.');
    }

    public function deleteQuote(Deal $deal, int $quoteId)
    {
        $quote = $deal->quotes()->findOrFail($quoteId);
        $quote->delete();
        return back()->with('success', 'Quote deleted.');
    }

    /**
     * Mark one quote as the accepted/selected one. Atomically clears
     * is_selected on every other quote for this deal so only one is
     * accepted at a time (xDeskPro parity — the accepted quote shows
     * the green ✓ Accepted badge on the Quotes tab).
     */
    public function acceptQuote(Deal $deal, int $quoteId)
    {
        \DB::transaction(function () use ($deal, $quoteId) {
            $deal->quotes()->where('id', '!=', $quoteId)->update(['is_selected' => false]);
            $deal->quotes()->where('id', $quoteId)->update(['is_selected' => true]);
        });
        return back()->with('success', 'Quote marked accepted.');
    }

    /**
     * Pull live OEM lease/finance/cash offers from MarketCheck for this
     * deal's make + customer ZIP. Returns normalized buckets the
     * calculator can pick from. Costs 1 MarketCheck call per pull.
     */
    public function pullLiveOffers(Deal $deal, \App\Services\MarketCheckOffersService $offers)
    {
        return response()->json($offers->offersForDeal($deal));
    }

    /**
     * Stream a zip of every uploaded document for a deal (Documents tab
     * "Download All" — xDeskPro parity).
     */
    public function downloadAllDocuments(Deal $deal)
    {
        $docs = $deal->documents;
        if ($docs->isEmpty()) {
            return back()->with('error', 'No documents to download.');
        }

        $zipName = "deal-{$deal->deal_number}-documents.zip";
        $tmpPath = tempnam(sys_get_temp_dir(), 'dealdocs_');
        $zip = new \ZipArchive();
        if ($zip->open($tmpPath, \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not create zip file.');
        }
        foreach ($docs as $doc) {
            if (!\Storage::exists($doc->path)) continue;
            $contents = \Storage::get($doc->path);
            // Prefix with type so the zip is self-organizing
            $folder = $doc->type ? str_replace('_', '-', $doc->type) : 'other';
            $name = ($doc->name ?: basename($doc->path));
            $zip->addFromString("{$folder}/{$doc->id}-{$name}", $contents);
        }
        $zip->close();

        return response()->download($tmpPath, $zipName)->deleteFileAfterSend(true);
    }

    /**
     * Returns a default SMS or email body for a quote, ready to be
     * shown in a preview modal so staff can review/edit before sending.
     * Returned as JSON so the modal can pre-fill the textarea.
     */
    public function previewSendQuote(Request $request, Deal $deal, int $quoteId)
    {
        $request->validate(['channel' => 'required|in:sms,email']);
        $quote = $deal->quotes()->with('lender')->findOrFail($quoteId);
        $deal->loadMissing('customer');

        $vehicle = trim(($deal->vehicle_year ?? '') . ' ' . ($deal->vehicle_make ?? '') . ' ' . ($deal->vehicle_model ?? '')) ?: 'your vehicle';
        $first   = $deal->customer?->first_name ?: 'there';
        $monthly = $quote->monthly_payment ? '$' . number_format((float) $quote->monthly_payment, 2) . '/mo' : '$/mo TBD';
        $term    = $quote->term ? "{$quote->term} mo" : '';
        $miles   = $quote->mileage_per_year ? number_format($quote->mileage_per_year) . ' mi/yr' : '';
        $das     = $quote->das ? '$' . number_format((float) $quote->das, 2) . ' due at signing' : '';
        $lender  = $quote->lender?->name;

        $bullets = array_filter([$monthly, $term, $miles, $das, $lender ? "Lender: {$lender}" : null]);
        $body = $request->input('channel') === 'sms'
            ? "Hi {$first}! Quote for {$vehicle}:\n• " . implode("\n• ", $bullets) . "\n\nReply YES to accept or text questions."
            : "Hi {$first},\n\nHere's your quote for {$vehicle}:\n\n" . implode("\n", array_map(fn ($b) => "  • {$b}", $bullets)) .
              "\n\nFinal terms subject to credit approval. Reply YES to accept or let us know if you have questions.\n\nThanks,\nAutoGo Leasing";

        $subject = "Your AutoGo quote — {$vehicle}";

        return response()->json([
            'channel' => $request->input('channel'),
            'to'      => $request->input('channel') === 'sms' ? $deal->customer?->phone : $deal->customer?->email,
            'subject' => $subject,
            'body'    => $body,
        ]);
    }

    public function sendQuote(Request $request, Deal $deal, int $quoteId)
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,email',
            'to'      => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'body'    => 'required|string|max:4000',
        ]);
        $quote = $deal->quotes()->findOrFail($quoteId);

        if ($validated['channel'] === 'sms') {
            try {
                app(\App\Services\TelebroadService::class)->sendSms($validated['to'], $validated['body']);
                \App\Models\CommunicationLog::create([
                    'subject_type' => Deal::class,
                    'subject_id'   => $deal->id,
                    'customer_id'  => $deal->customer_id,
                    'user_id'      => auth()->id(),
                    'channel'      => 'sms',
                    'direction'    => 'outbound',
                    'from'         => null,
                    'to'           => $validated['to'],
                    'body'         => $validated['body'],
                    'status'       => 'sent',
                    'sent_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                return back()->with('error', 'SMS send failed: ' . $e->getMessage());
            }
        } else {
            try {
                \Illuminate\Support\Facades\Mail::raw($validated['body'], function ($m) use ($validated) {
                    $m->to($validated['to'])->subject($validated['subject'] ?: 'Your AutoGo quote');
                });
                \App\Models\CommunicationLog::create([
                    'subject_type' => Deal::class,
                    'subject_id'   => $deal->id,
                    'customer_id'  => $deal->customer_id,
                    'user_id'      => auth()->id(),
                    'channel'      => 'email',
                    'direction'    => 'outbound',
                    'from'         => config('mail.from.address'),
                    'to'           => $validated['to'],
                    'subject'      => $validated['subject'],
                    'body'         => $validated['body'],
                    'status'       => 'sent',
                    'sent_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                return back()->with('error', 'Email send failed: ' . $e->getMessage());
            }
        }

        $quote->update(['notes' => trim(($quote->notes ?? '') . "\nSent to customer via {$validated['channel']} at " . now()->toIso8601String())]);
        return back()->with('success', 'Quote sent to customer via ' . strtoupper($validated['channel']) . '.');
    }

    /**
     * Upload a document for this deal. Routes to CustomerDocument under
     * the deal's customer — the docs (license, insurance, paystub, …) are
     * customer-owned and should be reusable across the customer's deals,
     * not duplicated per deal. Show.vue's Documents tab reads from
     * customer.documents to surface them on every deal page.
     */
    public function uploadDocument(Request $request, Deal $deal)
    {
        if (!$deal->customer_id) {
            return back()->with('error', 'No customer linked to this deal — cannot attach a document.');
        }
        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'label' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
            'file' => 'required|file|max:20480', // 20 MB cap
        ]);
        $path = $request->file('file')->store('customer-documents/' . $deal->customer_id, 'public');
        \App\Models\CustomerDocument::create([
            'customer_id'   => $deal->customer_id,
            'type'          => $validated['type'],
            'label'         => $validated['label'] ?? $request->file('file')->getClientOriginalName(),
            'disk'          => 'public',
            'path'          => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime_type'     => $request->file('file')->getMimeType(),
            'expires_at'    => $validated['expires_at'] ?? null,
            'uploaded_by'   => auth()->id(),
        ]);
        return back()->with('success', 'Document uploaded.');
    }

    public function decodeVin(Request $request)
    {
        $request->validate(['vin' => 'required|string|size:17']);
        $result = $this->leasing->decodeVin($request->vin);
        return response()->json($result);
    }
}
