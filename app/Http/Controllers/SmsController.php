<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Services\TelebroadService;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(private readonly TelebroadService $telebroad) {}

    /**
     * Send an SMS to a customer (or arbitrary number) and log it.
     * VERIFIED endpoint: POST /send/sms (params: sms_line, receiver, msgdata).
     * See docs/TELEBROAD.md.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'to'              => 'required|string|max:20',
            'message'         => 'nullable|string|max:1600',
            'customer_id'     => 'nullable|exists:customers,id',
            'subject_type'    => 'nullable|string',
            'subject_id'      => 'nullable|integer',
            'attachments.*'   => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,mp3,m4a,wav,webm',
        ]);

        if (!$this->telebroad->isConfigured()) {
            return back()->with('error', 'Telebroad is not configured. Add credentials in Settings.');
        }

        // Save uploaded attachments to public disk and pass URLs to Telebroad MMS
        $mediaUrls    = [];
        $mediaRecords = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $f) {
                $name = 'sms-' . now()->format('YmdHis') . '-' . \Str::random(6) . '.' . $f->getClientOriginalExtension();
                $path = $f->storeAs('sms-outbound', $name, 'public');
                $url  = url('/storage/' . $path);
                $mediaUrls[] = $url;
                $mediaRecords[] = ['url' => $url, 'name' => $f->getClientOriginalName(), 'mime' => $f->getMimeType()];
            }
        }

        $body = (string) ($validated['message'] ?? '');
        if ($body === '' && empty($mediaUrls)) {
            return back()->with('error', 'Message body or at least one attachment is required.');
        }

        $result = $this->telebroad->sendSms($validated['to'], $body, $mediaUrls);

        CommunicationLog::create([
            'subject_type' => $validated['subject_type'] ?? null,
            'subject_id'   => $validated['subject_id'] ?? null,
            'customer_id'  => $validated['customer_id'] ?? null,
            'user_id'      => auth()->id(),
            'channel'      => 'sms',
            'direction'    => 'outbound',
            'from'         => (string) config('services.telebroad.phone_number'),
            'to'           => $validated['to'],
            'body'         => $body,
            'attachments'  => !empty($mediaRecords) ? ['media' => $mediaRecords] : null,
            'external_ref' => $result['external_id'] ?? null,
            'status'       => ($result['success'] ?? false) ? 'sent' : 'failed',
            'sent_at'      => now(),
        ]);

        if (!($result['success'] ?? false)) {
            return back()->with('error', 'SMS failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        return back()->with('success', 'SMS sent.');
    }

    /** Return active SMS templates as JSON for the in-thread dropdown. */
    public function templates()
    {
        return response()->json([
            'data' => \App\Models\SmsTemplate::where('is_active', true)
                ->orderBy('category')->orderBy('label')
                ->get(['id', 'label', 'body', 'category']),
        ]);
    }
}
