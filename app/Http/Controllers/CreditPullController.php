<?php

namespace App\Http\Controllers;

use App\Models\CreditPull;
use App\Models\Customer;
use App\Models\Deal;
use App\Services\Credit700Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditPullController extends Controller
{
    public function __construct(private readonly Credit700Service $credit) {}

    public function index(Request $request)
    {
        $pulls = CreditPull::with(['customer', 'deal', 'pulledByUser'])
            ->when($request->customer_id, fn($q, $id) => $q->where('customer_id', $id))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('Credit/Index', [
            'pulls' => $pulls,
            'stats' => [
                'total' => CreditPull::count(),
                'soft' => CreditPull::soft()->count(),
                'this_month' => CreditPull::whereMonth('created_at', now()->month)->count(),
            ],
            'configured' => $this->credit->isConfigured(),
        ]);
    }

    public function create(Request $request)
    {
        $customer = $request->customer_id ? Customer::find($request->customer_id) : null;
        $deal = $request->deal_id ? Deal::find($request->deal_id) : null;

        // Get existing valid pulls for this customer
        $existingPulls = $customer
            ? CreditPull::where('customer_id', $customer->id)->valid()->orderByDesc('created_at')->get()
            : collect();

        return Inertia::render('Credit/Create', [
            'customer' => $customer,
            'deal' => $deal,
            'existingPulls' => $existingPulls,
            'configured' => $this->credit->isConfigured(),
        ]);
    }

    public function store(Request $request)
    {
        // AutoGo only does SOFT pulls. The dealer/lender handles hard pulls.
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'deal_id' => 'nullable|exists:deals,id',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string|size:2',
            'zip' => 'nullable|string',
        ]);

        $pull = CreditPull::create(array_merge($validated, [
            'type' => 'soft',
            'pulled_by' => auth()->id(),
            'permissible_purpose' => 'prequalification',
            'ip_address' => $request->ip(),
            'status' => 'pending',
        ]));

        $result = $this->credit->softPull($pull);

        if (!$result['success']) {
            return back()->with('error', 'Credit pull failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        $message = "Credit pull complete — Score: {$result['score']}";
        if ($result['mock'] ?? false) {
            $message .= ' (mock response — configure CREDIT700_API_KEY in .env for real pulls)';
        }

        return redirect()->route('credit.show', $pull)->with('success', $message);
    }

    public function show(CreditPull $creditPull)
    {
        $creditPull->load(['customer', 'deal', 'pulledByUser']);

        return Inertia::render('Credit/Show', [
            'pull' => $creditPull,
        ]);
    }

    /**
     * Customer-specific credit history page.
     */
    public function forCustomer(Customer $customer)
    {
        $pulls = $customer->creditPulls()
            ->with(['deal', 'pulledByUser'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Credit/CustomerHistory', [
            'customer' => $customer,
            'pulls' => $pulls,
            'latestValidPull' => $pulls->first(fn($p) => $p->is_valid),
        ]);
    }
}
