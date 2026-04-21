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
            'to'           => 'required|string|max:20',
            'message'      => 'required|string|max:1600',
            'customer_id'  => 'nullable|exists:customers,id',
            'subject_type' => 'nullable|string',
            'subject_id'   => 'nullable|integer',
        ]);

        if (!$this->telebroad->isConfigured()) {
            return back()->with('error', 'Telebroad is not configured. Add credentials in Settings.');
        }

        $result = $this->telebroad->sendSms($validated['to'], $validated['message']);

        CommunicationLog::create([
            'subject_type' => $validated['subject_type'] ?? null,
            'subject_id'   => $validated['subject_id'] ?? null,
            'customer_id'  => $validated['customer_id'] ?? null,
            'user_id'      => auth()->id(),
            'channel'      => 'sms',
            'direction'    => 'outbound',
            'from'         => (string) config('services.telebroad.phone_number'),
            'to'           => $validated['to'],
            'body'         => $validated['message'],
            'external_ref' => $result['external_id'] ?? null,
            'status'       => ($result['success'] ?? false) ? 'sent' : 'failed',
            'sent_at'      => now(),
        ]);

        if (!($result['success'] ?? false)) {
            return back()->with('error', 'SMS failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        return back()->with('success', 'SMS sent.');
    }
}
