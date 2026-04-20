<?php

namespace App\Http\Controllers;

use App\Models\BusinessDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BusinessDocumentController extends Controller
{
    public function index()
    {
        $docs = BusinessDocument::orderBy('category')->orderBy('name')->get();

        $grouped = $docs->groupBy('category');

        return Inertia::render('BusinessDocuments/Index', [
            'grouped' => $grouped,
            'stats' => [
                'total' => $docs->count(),
                'expired' => $docs->filter(fn($d) => $d->is_expired)->count(),
                'expiring_soon' => $docs->filter(fn($d) => $d->is_expiring_soon)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|in:general,high_rental,leasing,bodyshop',
            'document_number' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        BusinessDocument::create(array_merge($validated, ['created_by' => auth()->id()]));

        return back()->with('success', 'Document added.');
    }

    public function update(Request $request, BusinessDocument $businessDocument)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string',
            'document_number' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'status' => 'nullable|in:active,expiring_soon,expired,pending',
            'notes' => 'nullable|string',
        ]);

        $businessDocument->update($validated);

        return back()->with('success', 'Document updated.');
    }
}
