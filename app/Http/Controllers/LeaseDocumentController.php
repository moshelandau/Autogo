<?php

namespace App\Http\Controllers;

use App\Models\LeaseDocumentChecklist;
use App\Models\Customer;
use App\Models\Deal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaseDocumentController extends Controller
{
    public function index(Request $request)
    {
        $checklists = LeaseDocumentChecklist::with(['customer', 'deal', 'items'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $search) {
                $q->whereHas('customer', fn($q2) =>
                    $q2->where('first_name', 'ilike', "%{$search}%")
                       ->orWhere('last_name', 'ilike', "%{$search}%"));
            })
            ->orderByDesc('created_at')
            ->paginate(25)->withQueryString();

        return Inertia::render('Leasing/Documents/Index', [
            'checklists' => $checklists,
            'stats' => [
                'pending' => LeaseDocumentChecklist::where('status', 'pending')->count(),
                'complete' => LeaseDocumentChecklist::where('status', 'complete')->count(),
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Leasing/Documents/Create', [
            'customers' => Customer::where('is_active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'phone']),
            'deals' => Deal::active()->with('customer')->orderByDesc('created_at')->get(['id', 'deal_number', 'customer_id', 'vehicle_make', 'vehicle_model']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'deal_id' => 'nullable|exists:deals,id',
            'notes' => 'nullable|string',
        ]);

        $checklist = LeaseDocumentChecklist::create(array_merge($validated, [
            'created_by' => auth()->id(),
        ]));

        $checklist->generateItems();

        return redirect()->route('leasing.documents.show', $checklist)
            ->with('success', 'Document checklist created with 6 items.');
    }

    public function show(LeaseDocumentChecklist $checklist)
    {
        $checklist->load(['customer', 'deal', 'items']);

        return Inertia::render('Leasing/Documents/Show', ['checklist' => $checklist]);
    }

    public function toggleItem(LeaseDocumentChecklist $checklist, int $itemId)
    {
        $item = $checklist->items()->findOrFail($itemId);

        if ($item->is_collected) {
            $item->markUncollected();
        } else {
            $item->markCollected();
        }

        return back();
    }
}
