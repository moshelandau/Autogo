<?php

namespace App\Http\Controllers;

use App\Models\AgreementRevision;
use App\Models\CommunicationLog;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AgreementRevisionController extends Controller
{
    public function listForReservation(Reservation $reservation)
    {
        return response()->json([
            'revisions' => AgreementRevision::where('reservation_id', $reservation->id)
                ->with('createdBy:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn($r) => [
                    'id'            => $r->id,
                    'document_type' => $r->document_type,
                    'action'        => $r->action,
                    'created_at'    => $r->created_at,
                    'created_by'    => $r->createdBy?->name ?? 'System',
                    'sha256_short'  => substr($r->sha256, 0, 12),
                    'pdf_url'       => $r->pdf_path ? Storage::disk('public')->url($r->pdf_path) : null,
                    'download_url' => route('reservations.revisions.download', $r->id),
                    'email_url'    => route('reservations.revisions.email', $r->id),
                ]),
        ]);
    }

    public function download(AgreementRevision $revision)
    {
        abort_unless(Storage::disk('public')->exists($revision->pdf_path), 404);
        $name = ($revision->document_type === 'return_receipt' ? 'return-receipt-' : 'rental-agreement-')
            . $revision->reservation_id . '-' . $revision->id . '.pdf';
        return Storage::disk('public')->download($revision->pdf_path, $name);
    }

    public function email(Request $request, AgreementRevision $revision)
    {
        $request->validate([
            'to'      => 'nullable|email',
            'message' => 'nullable|string',
        ]);

        $reservation = $revision->reservation()->with('customer')->first();
        $to = $request->input('to') ?: $reservation?->customer?->email;
        if (!$to) return back()->with('error', 'No email address on file.');

        $pdfAbsolute = storage_path('app/public/' . $revision->pdf_path);
        if (!file_exists($pdfAbsolute)) return back()->with('error', 'PDF file missing.');

        try {
            Mail::raw($request->input('message') ?: 'Attached is your rental document from High Car Rental.', function ($m) use ($to, $pdfAbsolute, $revision) {
                $m->to($to)->subject('High Car Rental — ' . str_replace('_', ' ', $revision->document_type))
                  ->attach($pdfAbsolute);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Email failed: ' . $e->getMessage());
        }

        CommunicationLog::create([
            'subject_type' => Reservation::class,
            'subject_id'   => $revision->reservation_id,
            'customer_id'  => $reservation?->customer_id,
            'user_id'      => auth()->id(),
            'channel'      => 'email',
            'direction'    => 'outbound',
            'from'         => config('mail.from.address'),
            'to'           => $to,
            'subject'      => 'High Car Rental — ' . str_replace('_', ' ', $revision->document_type),
            'body'         => $request->input('message'),
            'attachments'  => [['path' => $revision->pdf_path, 'sha256' => $revision->sha256]],
            'status'       => 'sent',
            'sent_at'      => now(),
        ]);

        return back()->with('success', "Emailed to $to");
    }
}
