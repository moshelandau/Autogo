<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\LeaseApplicationSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * Editable on-screen version of the verified AutoGo Leasing Application PDF.
 * Source layout: docs/AutoGo Leasing Application_260419.pdf.
 *
 * Aggregates fields from:
 *   - the linked Customer record (identity + address + DL + insurance)
 *   - the most-recent LeaseApplicationSession.collected (bot intake answers,
 *     including co-applicant + employment)
 *   - the Deal record (vehicle interest)
 *
 * Edits auto-save per field, written back to the right underlying source.
 * Generates a PDF (DomPDF) and can email it to a dealer.
 */
class LeaseApplicationFormController extends Controller
{
    public function show(Deal $deal)
    {
        return Inertia::render('Leasing/ApplicationForm', $this->buildPayload($deal));
    }

    public function update(Request $request, Deal $deal)
    {
        $request->validate(['key' => 'required|string|max:60', 'value' => 'nullable|string']);
        $key = $request->input('key');
        $value = (string) $request->input('value', '');

        $deal->load('customer');
        $cu = $deal->customer;

        $customerMap = [
            'applicant_dob' => 'date_of_birth',
            'applicant_phone' => 'phone',
            'applicant_address' => 'address',
            'applicant_city' => 'city',
            'applicant_state' => 'state',
            'applicant_zip' => 'zip',
            'applicant_employer_email' => 'email',
        ];

        if ($key === 'applicant_name' && $cu) {
            $parts = preg_split('/\s+/', trim($value), 2);
            $cu->first_name = $parts[0] ?? '';
            $cu->last_name  = $parts[1] ?? '';
            $cu->save();
        } elseif (isset($customerMap[$key]) && $cu) {
            $col = $customerMap[$key];
            if ($col === 'date_of_birth' && $value) {
                try { $value = \Carbon\Carbon::parse($value)->toDateString(); } catch (\Throwable) {}
            }
            $cu->{$col} = $value ?: null;
            $cu->save();
        } elseif ($key === 'vehicle_interest' || $key === 'signature_data_url') {
            $session = $this->ensureSession($deal);
            $collected = $session->collected ?? [];
            $collected[$key] = $value;
            if ($key === 'signature_data_url' && $value) $collected['signature_at'] = now()->toIso8601String();
            $session->update(['collected' => $collected]);
        } else {
            $session = $this->ensureSession($deal);
            $collected = $session->collected ?? [];
            $sessionMap = [
                'applicant_ssn' => 'ssn',
                'applicant_own_or_rent' => 'own_or_rent',
                'applicant_monthly_housing' => 'monthly_housing',
                'applicant_years_at_addr' => 'years_at_address',
                'applicant_employer' => 'employer',
                'applicant_employer_address' => 'employer_address',
                'applicant_employer_city' => 'employer_city',
                'applicant_employer_state' => 'employer_state',
                'applicant_employer_zip' => 'employer_zip',
                'applicant_employer_phone' => 'employer_phone',
                'applicant_position' => 'position',
                'applicant_years_employed' => 'years_employed',
                'applicant_annual_income' => 'annual_income',
                'co_name' => '__co_name__',
                'co_dob' => 'co_date_of_birth',
                'co_ssn' => 'co_ssn',
                'co_phone' => 'co_phone',
                'co_address' => 'co_address',
                'co_city' => 'co_city',
                'co_state' => 'co_state',
                'co_zip' => 'co_zip',
                'co_own_or_rent' => 'co_own_or_rent',
                'co_monthly_housing' => 'co_monthly_housing',
                'co_years_at_addr' => 'co_years_at_address',
                'co_employer' => 'co_employer',
                'co_employer_address' => 'co_employer_address',
                'co_employer_city' => 'co_employer_city',
                'co_employer_state' => 'co_employer_state',
                'co_employer_zip' => 'co_employer_zip',
                'co_employer_phone' => 'co_employer_phone',
                'co_employer_email' => 'co_employer_email',
                'co_position' => 'co_position',
                'co_years_employed' => 'co_years_employed',
                'co_annual_income' => 'co_annual_income',
            ];
            $sk = $sessionMap[$key] ?? null;
            if ($sk === '__co_name__') {
                $parts = preg_split('/\s+/', trim($value), 2);
                $collected['co_first_name'] = $parts[0] ?? '';
                $collected['co_last_name']  = $parts[1] ?? '';
            } elseif ($sk) {
                $collected[$sk] = $value;
            }
            $session->update(['collected' => $collected]);
        }

        return back()->with('success', 'Saved.');
    }

    public function pdf(Deal $deal)
    {
        $payload = $this->buildPayload($deal);
        $pdf = Pdf::loadView('leasing.application_pdf', $payload)->setPaper('letter');
        return $pdf->stream("AutoGo-Application-{$deal->deal_number}.pdf");
    }

    public function approveField(Request $request, Deal $deal)
    {
        $request->validate(['key' => 'required|string|max:60', 'approve' => 'required|boolean']);
        $session = $this->ensureSession($deal);
        $approvals = $session->approvals ?? [];
        if ($request->boolean('approve')) {
            $approvals[$request->input('key')] = ['by' => auth()->id(), 'at' => now()->toIso8601String()];
        } else {
            unset($approvals[$request->input('key')]);
        }
        $session->update(['approvals' => $approvals]);
        return back()->with('success', $request->boolean('approve') ? 'Approved.' : 'Approval cleared.');
    }

    public function emailToDealer(Request $request, Deal $deal)
    {
        $session = $this->ensureSession($deal);
        $approvals = $session->approvals ?? [];
        $missing = array_diff(\App\Models\LeaseApplicationSession::APPROVAL_REQUIRED, array_keys($approvals));
        if (!empty($missing)) {
            return back()->with('error', 'Cannot email — these fields still need staff approval: ' . implode(', ', $missing));
        }

        $data = $request->validate([
            'to'      => 'required|email',
            'subject' => 'nullable|string|max:200',
            'message' => 'nullable|string',
        ]);

        $payload = $this->buildPayload($deal);
        $pdf = Pdf::loadView('leasing.application_pdf', $payload)->setPaper('letter');
        $pdfPath = storage_path("app/tmp-application-{$deal->deal_number}.pdf");
        @mkdir(dirname($pdfPath), 0775, true);
        $pdf->save($pdfPath);

        try {
            Mail::raw($data['message'] ?? "Please find attached the leasing application for review.", function ($m) use ($data, $deal, $pdfPath) {
                $m->to($data['to'])
                  ->subject($data['subject'] ?? "AutoGo Leasing Application — Deal #{$deal->deal_number}")
                  ->attach($pdfPath, ['as' => "AutoGo-Application-{$deal->deal_number}.pdf", 'mime' => 'application/pdf']);
            });
        } finally {
            @unlink($pdfPath);
        }

        return back()->with('success', "Application emailed to {$data['to']}");
    }

    private function ensureSession(Deal $deal): LeaseApplicationSession
    {
        $session = LeaseApplicationSession::where('customer_id', $deal->customer_id)->latest('id')->first();
        if (!$session) {
            $session = LeaseApplicationSession::create([
                'phone' => $deal->customer?->phone ?? '', 'flow' => 'lease',
                'current_step' => '__done__', 'collected' => [],
                'customer_id' => $deal->customer_id, 'deal_id' => $deal->id,
                'completed_at' => now(),
            ]);
        }
        return $session;
    }

    private function buildPayload(Deal $deal): array
    {
        $deal->load(['customer', 'coSigner']);
        // Prefer the session that produced THIS deal; otherwise the latest
        // completed session for the customer; otherwise any session.
        $session = LeaseApplicationSession::where('deal_id', $deal->id)->latest('id')->first()
            ?? LeaseApplicationSession::where('customer_id', $deal->customer_id)
                ->whereNotNull('completed_at')->latest('id')->first()
            ?? LeaseApplicationSession::where('customer_id', $deal->customer_id)
                ->latest('id')->first();
        $c = $session?->collected ?? [];
        $cu = $deal->customer;

        $fields = [
            'applicant_name'           => trim(($cu?->first_name ?? '') . ' ' . ($cu?->last_name ?? '')),
            'applicant_dob'            => optional($cu?->date_of_birth)->format('m/d/Y') ?: ($c['date_of_birth'] ?? ''),
            'applicant_ssn'            => $c['ssn'] ?? '',
            'applicant_phone'          => $cu?->phone ?? '',
            'applicant_address'        => $cu?->address ?? ($c['address'] ?? ''),
            'applicant_city'           => $cu?->city ?? ($c['city'] ?? ''),
            'applicant_state'          => $cu?->state ?? ($c['state'] ?? ''),
            'applicant_zip'            => $cu?->zip ?? ($c['zip'] ?? ''),
            'applicant_own_or_rent'    => $c['own_or_rent'] ?? '',
            'applicant_monthly_housing'=> $c['monthly_housing'] ?? '',
            'applicant_years_at_addr'  => $c['years_at_address'] ?? '',
            'applicant_employer'         => $c['employer'] ?? '',
            'applicant_employer_address' => $c['employer_address'] ?? '',
            'applicant_employer_city'    => $c['employer_city'] ?? '',
            'applicant_employer_state'   => $c['employer_state'] ?? '',
            'applicant_employer_zip'     => $c['employer_zip'] ?? '',
            'applicant_employer_phone'   => $c['employer_phone'] ?? '',
            'applicant_employer_email'   => $cu?->email ?? '',
            'applicant_position'         => $c['position'] ?? '',
            'applicant_years_employed'   => $c['years_employed'] ?? '',
            'applicant_annual_income'    => $c['annual_income'] ?? '',
            // Co-signer: prefer session-collected values (the SMS bot fills these),
            // fall back to the linked Customer record (when staff added a co-signer
            // via the Workflow tab — that flow only sets deal.co_signer_customer_id
            // and never writes to session.collected).
            'co_name'           => trim(($c['co_first_name'] ?? '') . ' ' . ($c['co_last_name'] ?? ''))
                                       ?: trim(($deal->coSigner?->first_name ?? '') . ' ' . ($deal->coSigner?->last_name ?? '')),
            'co_dob'            => $c['co_date_of_birth'] ?? (optional($deal->coSigner?->date_of_birth)->format('m/d/Y') ?: ''),
            'co_ssn'            => $c['co_ssn'] ?? '',
            'co_phone'          => $c['co_phone'] ?? ($deal->coSigner?->phone ?? ''),
            'co_address'        => $c['co_address'] ?? ($deal->coSigner?->address ?? ''),
            'co_city'           => $c['co_city'] ?? ($deal->coSigner?->city ?? ''),
            'co_state'          => $c['co_state'] ?? ($deal->coSigner?->state ?? ''),
            'co_zip'            => $c['co_zip'] ?? ($deal->coSigner?->zip ?? ''),
            'co_own_or_rent'    => $c['co_own_or_rent'] ?? '',
            'co_monthly_housing'=> $c['co_monthly_housing'] ?? '',
            'co_years_at_addr'  => $c['co_years_at_address'] ?? '',
            'co_employer'         => $c['co_employer'] ?? '',
            'co_employer_address' => $c['co_employer_address'] ?? '',
            'co_employer_city'    => $c['co_employer_city'] ?? '',
            'co_employer_state'   => $c['co_employer_state'] ?? '',
            'co_employer_zip'     => $c['co_employer_zip'] ?? '',
            'co_employer_phone'   => $c['co_employer_phone'] ?? '',
            'co_employer_email'   => $c['co_employer_email'] ?? '',
            'co_position'         => $c['co_position'] ?? '',
            'co_years_employed'   => $c['co_years_employed'] ?? '',
            'co_annual_income'    => $c['co_annual_income'] ?? '',
            'vehicle_interest' => $c['vehicle_interest'] ?? trim(($deal->vehicle_year ?? '') . ' ' . ($deal->vehicle_make ?? '') . ' ' . ($deal->vehicle_model ?? '')),
        ];

        $fields['signature_data_url'] = $c['signature_data_url'] ?? '';
        $fields['signature_at']       = $c['signature_at'] ?? '';

        return [
            'deal' => $deal,
            'session' => $session,
            'fields' => $fields,
            'approvals' => $session?->approvals ?? [],
            'approvalRequired' => \App\Models\LeaseApplicationSession::APPROVAL_REQUIRED,
        ];
    }
}
