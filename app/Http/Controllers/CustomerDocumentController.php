<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerDocumentController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type'  => 'required|string|in:' . implode(',', array_keys(CustomerDocument::TYPES)),
            'label' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
            'file'  => 'required|file|mimes:jpg,jpeg,png,pdf,heic,webp|max:10240', // 10MB
        ]);

        $disk = 'public';
        $path = $request->file('file')->store("customers/{$customer->id}", $disk);

        $doc = $customer->documents()->create([
            'type'          => $validated['type'],
            'label'         => $validated['label'] ?? null,
            'expires_at'    => $validated['expires_at'] ?? null,
            'disk'          => $disk,
            'path'          => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime_type'     => $request->file('file')->getMimeType(),
            'size_bytes'    => $request->file('file')->getSize(),
            'uploaded_by'   => auth()->id(),
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function destroy(Customer $customer, CustomerDocument $document)
    {
        abort_unless($document->customer_id === $customer->id, 404);
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
        return back()->with('success', 'Document removed.');
    }
}
