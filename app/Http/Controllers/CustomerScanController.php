<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CustomerScanController extends Controller
{
    /**
     * Extract fields from a generic ID/CC/insurance image without persisting.
     * Returns { type, fields } so the caller can route to the right pre-fill
     * (license -> customer fields, credit_card -> CC entry, insurance ->
     * customer.insurance_*).
     *
     * Pass ?expect=license|credit_card|insurance to bias classification.
     */
    public function extractAny(Request $request)
    {
        $request->validate(['file' => 'required|file|image|max:10240']);
        if (empty(config('services.anthropic.api_key'))) {
            return response()->json(['ok' => false, 'error' => 'Anthropic API key not configured'], 422);
        }

        $f = $request->file('file');
        $b64  = base64_encode(file_get_contents($f->getRealPath()));
        $mime = $f->getMimeType() ?: 'image/jpeg';
        $expect = $request->input('expect', 'auto');

        try {
            $resp = app(\App\Services\AiClient::class)->messages([
                'model'       => 'claude-3-5-sonnet-latest',
                'max_tokens'  => 800,
                'temperature' => 0,
                'system'      => 'OCR cards/IDs. Output VALID JSON only.',
                'messages'    => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]],
                        ['type' => 'text', 'text' => 'Identify the document and extract fields. Output JSON: {
                            "type": "drivers_license" | "credit_card" | "insurance_card" | "other",
                            "fields": {
                                "first_name": "", "last_name": "", "address": "", "city": "", "state": "", "zip": "",
                                "drivers_license_number": "", "dl_state": "", "dl_expiration": "YYYY-MM-DD", "date_of_birth": "YYYY-MM-DD",
                                "card_number": "", "card_brand": "visa|mc|amex|discover", "card_exp": "MM/YY", "cardholder": "",
                                "insurance_company": "", "insurance_policy": "", "insurance_expiration": "YYYY-MM-DD"
                            }
                        }. Use empty string for missing fields. Caller hint: '.$expect.'.'],
                    ],
                ]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (!is_array($data)) return response()->json(['ok' => false, 'error' => 'Could not parse'], 422);
            return response()->json(['ok' => true, 'type' => $data['type'] ?? 'other', 'fields' => $data['fields'] ?? []]);
        } catch (\Throwable $e) {
            Log::warning('Scan extract failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Extract fields from a driver's license / ID image WITHOUT persisting
     * anything. Used by the "Scan License" button in customer-create modals
     * (CustomerSelect, reservation flows, deals, etc.) to pre-fill the form.
     */
    public function extractLicense(Request $request)
    {
        $request->validate([
            'image_base64' => 'required_without:file|nullable|string',
            'file'         => 'required_without:image_base64|nullable|file|image|max:10240',
        ]);

        if (empty(config('services.anthropic.api_key'))) {
            return response()->json(['ok' => false, 'error' => 'Anthropic API key not configured'], 422);
        }

        // Resolve image into base64 + mime
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $b64  = base64_encode(file_get_contents($f->getRealPath()));
            $mime = $f->getMimeType() ?: 'image/jpeg';
        } else {
            $raw = (string) $request->input('image_base64');
            $b64  = preg_replace('#^data:image/[^;]+;base64,#', '', $raw);
            $mime = preg_match('#^data:(image/[^;]+);#', $raw, $m) ? $m[1] : 'image/jpeg';
        }

        try {
            $resp = app(\App\Services\AiClient::class)->messages([
                'model'       => 'claude-3-5-sonnet-latest',
                'max_tokens'  => 800,
                'temperature' => 0,
                'system'      => 'You are an OCR system for US driver licenses and government IDs. Output VALID JSON ONLY, no prose, no code fences.',
                'messages'    => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]],
                        ['type' => 'text', 'text' => 'Extract every field from this driver license / ID. Output JSON: { "first_name": "", "last_name": "", "middle_name": "", "address": "", "city": "", "state": "", "zip": "", "drivers_license_number": "", "dl_state": "", "dl_expiration": "YYYY-MM-DD", "dl_issued": "YYYY-MM-DD", "date_of_birth": "YYYY-MM-DD", "sex": "", "height": "", "eye_color": "", "endorsements": "", "restrictions": "", "class": "", "is_real_id": true_or_false }. Use empty string for missing fields. Use uppercase 2-letter abbreviations for state. Dates in YYYY-MM-DD. If the image is not a license/ID return {"error":"not_a_license"}.'],
                    ],
                ]],
            ]);

            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (!is_array($data)) return response()->json(['ok' => false, 'error' => 'Could not parse extraction', 'raw' => $text], 422);
            if (!empty($data['error'])) return response()->json(['ok' => false, 'error' => $data['error']], 422);

            return response()->json(['ok' => true, 'fields' => $data]);
        } catch (\Throwable $e) {
            Log::warning('License extract failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Customer $customer)
    {
        return Inertia::render('Customers/Scan', [
            'customer'      => $customer,
            'documentTypes' => CustomerDocument::TYPES,
        ]);
    }

    /**
     * Receive base64 image from Plustek scanner, save it, and ask Claude Vision
     * to classify what kind of document it is (DL front/back, insurance, CC, etc.).
     */
    public function ingest(Request $request, Customer $customer)
    {
        $request->validate([
            'image_base64' => 'required|string',
            'mime'         => 'nullable|string',
            'page_index'   => 'nullable|integer',
            'manual_type'  => 'nullable|string|in:'.implode(',', array_keys(CustomerDocument::TYPES)),
        ]);

        // Decode + store the image
        $b64 = preg_replace('#^data:image/[^;]+;base64,#', '', $request->input('image_base64'));
        $binary = base64_decode($b64);
        if ($binary === false) abort(422, 'Invalid base64');

        $filename = 'scan-'.now()->format('YmdHis').'-'.Str::random(6).'.jpg';
        $path = "customers/{$customer->id}/{$filename}";
        Storage::disk('public')->put($path, $binary);

        // Classify (skip if manual override or no Anthropic key)
        $detectedType   = $request->input('manual_type');
        $detectedFields = [];
        if (!$detectedType && !empty(config('services.anthropic.api_key'))) {
            try {
                [$detectedType, $detectedFields] = $this->classifyWithClaude($b64, $request->input('mime', 'image/jpeg'));
            } catch (\Throwable $e) {
                Log::warning('Claude classify failed', ['error' => $e->getMessage()]);
            }
        }

        $doc = $customer->documents()->create([
            'type'          => $detectedType ?: 'other',
            'label'         => $this->labelFromFields($detectedType, $detectedFields),
            'disk'          => 'public',
            'path'          => $path,
            'original_name' => $filename,
            'mime_type'     => $request->input('mime', 'image/jpeg'),
            'size_bytes'    => strlen($binary),
            'expires_at'    => $detectedFields['expiration'] ?? null,
            'uploaded_by'   => auth()->id(),
        ]);

        // Optionally apply discovered fields back to the customer record
        $this->maybeUpdateCustomerFromScan($customer, $detectedType, $detectedFields);

        return response()->json([
            'ok' => true,
            'document' => $doc->fresh(),
            'detected_type'   => $detectedType,
            'detected_fields' => $detectedFields,
        ]);
    }

    /**
     * Use Claude vision to identify what document this is and extract fields.
     */
    private function classifyWithClaude(string $b64, string $mime): array
    {
        $resp = app(\App\Services\AiClient::class)->messages([
            'model'       => 'claude-3-5-sonnet-latest',
            'max_tokens'  => 600,
            'temperature' => 0,
            'system' => 'You are an OCR + classifier. Identify the document type and extract key fields. Output VALID JSON ONLY, no prose.',
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Classify this scanned document. Output JSON: { "type": one of [drivers_license_front, drivers_license_back, passport, insurance_card, registration, proof_of_residence, paystub, w2, utility_bill, lease_agreement, credit_card, other], "fields": { ... extracted fields like name, dl_number, expiration (YYYY-MM-DD), state, insurance_company, policy_number, card_brand, card_last4 } }. Only output the JSON object, nothing else.',
                    ],
                ],
            ]],
        ]);

        $text = $resp->content[0]->text ?? '';
        // Strip code fences if any
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text));
        $data = json_decode($text, true);
        return [
            $data['type']   ?? 'other',
            $data['fields'] ?? [],
        ];
    }

    private function labelFromFields(?string $type, array $fields): ?string
    {
        return match ($type) {
            'drivers_license_front', 'drivers_license_back'
                => trim(($fields['name'] ?? '') . ' · ' . ($fields['dl_number'] ?? '') . ' · ' . ($fields['state'] ?? ''), ' ·'),
            'insurance_card'
                => trim(($fields['insurance_company'] ?? '') . ' · ' . ($fields['policy_number'] ?? ''), ' ·'),
            'credit_card'
                => trim(($fields['card_brand'] ?? '') . ' •••• ' . ($fields['card_last4'] ?? ''), ' '),
            default => null,
        };
    }

    /**
     * Apply scan-extracted data back to the Customer record (DL #, insurance) when missing.
     */
    private function maybeUpdateCustomerFromScan(Customer $customer, ?string $type, array $fields): void
    {
        $updates = [];
        if (in_array($type, ['drivers_license_front', 'drivers_license_back'], true)) {
            if (!empty($fields['dl_number'])  && empty($customer->drivers_license_number)) $updates['drivers_license_number'] = $fields['dl_number'];
            if (!empty($fields['state'])      && empty($customer->dl_state))               $updates['dl_state'] = strtoupper(substr($fields['state'], 0, 2));
            if (!empty($fields['expiration']) && empty($customer->dl_expiration))          $updates['dl_expiration'] = $fields['expiration'];
            if (!empty($fields['date_of_birth']) && empty($customer->date_of_birth))       $updates['date_of_birth'] = $fields['date_of_birth'];
        }
        if ($type === 'insurance_card') {
            if (!empty($fields['insurance_company']) && empty($customer->insurance_company)) $updates['insurance_company'] = $fields['insurance_company'];
            if (!empty($fields['policy_number'])     && empty($customer->insurance_policy))  $updates['insurance_policy']  = $fields['policy_number'];
        }
        if ($updates) $customer->update($updates);
    }
}
