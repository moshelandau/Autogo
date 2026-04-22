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

        // Save attachments to public disk + build the {url, type, name} objects
        // Telebroad expects (verified live: string-only arrays return code 444).
        $mediaForApi      = [];   // → Telebroad
        $mediaForLog      = [];   // → CommunicationLog.attachments.media
        $bodyExtras       = [];   // appended to the SMS body for non-MMS-friendly types (PDF)
        if ($request->hasFile('attachments')) {
            $resizer = app(\App\Services\ImageResizer::class);
            foreach ($request->file('attachments') as $f) {
                $origMime = (string) $f->getMimeType();
                $origName = $f->getClientOriginalName();
                $bytes    = file_get_contents($f->getRealPath());

                // Browser-recorded webm voice notes sometimes report video/webm even
                // though they're audio-only — normalize.
                $mime = str_contains($origMime, 'webm') ? 'audio/webm' : $origMime;

                // Auto-shrink images so MMS payload stays under most carriers' cap (~600KB).
                $extOut = $f->getClientOriginalExtension() ?: 'bin';
                $resizeNote = '';
                if (str_starts_with($mime, 'image/') && strlen($bytes) > \App\Services\ImageResizer::TARGET_MAX_BYTES) {
                    $r = $resizer->fitForMms($bytes, $mime);
                    if ($r['resized']) {
                        $oldKb = (int) round(strlen($bytes) / 1024);
                        $newKb = (int) round(strlen($r['bytes']) / 1024);
                        $bytes  = $r['bytes'];
                        $mime   = $r['mime'];
                        $extOut = $r['extension'];
                        $resizeNote = " (resized {$oldKb}KB→{$newKb}KB)";
                    }
                }

                $name = 'sms-' . now()->format('YmdHis') . '-' . \Str::random(6) . '.' . $extOut;
                \Storage::disk('public')->put('sms-outbound/' . $name, $bytes);
                $url = url('/storage/sms-outbound/' . $name);

                $mediaForLog[] = ['url' => $url, 'name' => $origName . $resizeNote, 'mime' => $mime];

                if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'audio/')) {
                    $mediaForApi[] = ['url' => $url, 'type' => $mime];
                } else {
                    // PDF / other — Telebroad MMS doesn't reliably accept these.
                    // Send as a download link in the SMS body instead.
                    $bodyExtras[] = "📎 {$origName}: {$url}";
                }
            }
        }

        $body = trim((string) ($validated['message'] ?? ''));
        if (!empty($bodyExtras)) {
            $body = trim($body . "\n" . implode("\n", $bodyExtras));
        }
        if ($body === '' && empty($mediaForApi)) {
            return back()->with('error', 'Message body or at least one attachment is required.');
        }

        $result = $this->telebroad->sendSms($validated['to'], $body, $mediaForApi);

        // For the log, use whatever Caller-ID Telebroad ended up using
        // (DB Setting first, env fallback) — same lookup the service does.
        $fromNumber = (string) (\App\Models\Setting::getValue('telebroad_phone_number')
            ?: config('services.telebroad.phone_number', ''));

        CommunicationLog::create([
            'subject_type' => $validated['subject_type'] ?? null,
            'subject_id'   => $validated['subject_id'] ?? null,
            'customer_id'  => $validated['customer_id'] ?? null,
            'user_id'      => auth()->id(),
            'channel'      => 'sms',
            'direction'    => 'outbound',
            'from'         => $fromNumber,
            'to'           => $validated['to'],
            'body'         => $body,
            'attachments'  => !empty($mediaForLog) ? ['media' => $mediaForLog] : null,
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
