<?php

namespace App\Http\Controllers;

use App\Models\RentalClaim;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RentalClaimController extends Controller
{
    public function index(Request $request)
    {
        $claims = RentalClaim::with(['customer', 'vehicle', 'reservation'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->brand, fn($q, $b) => $q->where('brand', $b))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->whereHas('customer', fn($q3) => $q3->search($search))
                       ->orWhere('insurance_claim_number', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)->withQueryString();

        return Inertia::render('RentalClaims/Index', [
            'claims' => $claims,
            'stats' => [
                'new' => RentalClaim::where('status', 'new')->count(),
                'pending_documents' => RentalClaim::where('status', 'pending_documents')->count(),
                'completed' => RentalClaim::where('status', 'completed')->count(),
                'approved' => RentalClaim::where('status', 'approved')->count(),
            ],
            'filters' => $request->only(['search', 'status', 'brand']),
        ]);
    }

    public function create()
    {
        return Inertia::render('RentalClaims/Create', [
            'customers' => Customer::where('is_active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'phone']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('make')->get(['id', 'year', 'make', 'model', 'license_plate']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'brand' => 'nullable|in:high_rental,mm_car_rental',
            'priority' => 'nullable|in:low,medium,high',
            'damage_description' => 'nullable|string',
            'incident_date' => 'nullable|date',
            'damage_amount' => 'nullable|numeric|min:0',
            'deductible_amount' => 'nullable|numeric|min:0',
            'insurance_company' => 'nullable|string|max:255',
            'insurance_claim_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $claim = RentalClaim::create(array_merge($validated, [
            'status' => 'new',
            'created_by' => auth()->id(),
        ]));

        return redirect()->route('rental-claims.show', $claim)->with('success', 'Rental claim created.');
    }

    public function show(RentalClaim $rentalClaim)
    {
        $rentalClaim->load(['customer', 'vehicle', 'reservation', 'documents', 'comments.user']);

        return Inertia::render('RentalClaims/Show', ['claim' => $rentalClaim]);
    }

    public function updateStatus(Request $request, RentalClaim $rentalClaim)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,pending_documents,completed,approved',
        ]);

        $rentalClaim->update($validated);
        return back()->with('success', 'Status updated.');
    }

    public function addComment(Request $request, RentalClaim $rentalClaim)
    {
        $validated = $request->validate(['body' => 'required|string']);
        $rentalClaim->comments()->create(['body' => $validated['body'], 'user_id' => auth()->id()]);
        return back()->with('success', 'Comment added.');
    }

    /**
     * Upload damage photos to a rental claim.
     * Supports single or multiple files (form key: photos[] or photo).
     */
    public function uploadPhoto(Request $request, RentalClaim $rentalClaim)
    {
        $request->validate([
            'photos'   => 'nullable|array',
            'photos.*' => 'image|mimes:jpg,jpeg,png,heic,webp|max:15360',
            'photo'    => 'nullable|image|mimes:jpg,jpeg,png,heic,webp|max:15360',
            'name'     => 'nullable|string|max:255',
        ]);

        $files = $request->file('photos', []);
        if ($single = $request->file('photo')) $files[] = $single;
        if (empty($files)) abort(422, 'No photo provided');

        foreach ($files as $f) {
            $path = $f->store("rental-claims/{$rentalClaim->id}", 'public');
            $rentalClaim->documents()->create([
                'name'        => $request->input('name') ?: $f->getClientOriginalName(),
                'type'        => 'damage_photo',
                'path'        => $path,
                'uploaded_by' => auth()->id(),
            ]);
        }
        return back()->with('success', count($files).' photo(s) uploaded.');
    }

    public function deletePhoto(RentalClaim $rentalClaim, \App\Models\RentalClaimDocument $document)
    {
        abort_unless($document->rental_claim_id === $rentalClaim->id, 404);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($document->path);
        $document->delete();
        return back()->with('success', 'Photo deleted.');
    }
}
