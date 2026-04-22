<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgreementRevision;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;

class AgreementRevisionService
{
    /**
     * Generate a new PDF snapshot of the rental agreement (or return receipt)
     * for the reservation, link it to a business event ("action"), and append
     * to the hash-chain.
     */
    public function snapshot(Reservation $reservation, string $action, string $documentType = 'rental_agreement'): AgreementRevision
    {
        // DomPDF is memory-hungry, especially with large signature data URLs
        // and embedded images. Bump for this request only.
        @ini_set('memory_limit', '512M');

        $reservation->loadMissing(['customer', 'vehicle', 'activeHold', 'payments', 'holds']);

        $view = $documentType === 'return_receipt' ? 'rental.return_receipt' : 'rental.agreement';

        // Find the most recent accepted signature for this reservation
        $signature = \App\Models\Signature::where('signable_type', Reservation::class)
            ->where('signable_id', $reservation->id)
            ->orderByDesc('signed_at')->first();

        $pdf = Pdf::loadView($view, [
            'reservation'      => $reservation,
            'customer'         => $reservation->customer,
            'activeHold'       => $reservation->activeHold,
            'signatureDataUrl' => $signature?->signature_data_url,
            'signature'        => $signature,
            'action'           => $action,
            'documentType'     => $documentType,
        ])->setPaper('letter', 'portrait');

        $binary = $pdf->output();
        $sha = hash('sha256', $binary);

        $filename = sprintf(
            '%s-%s-%s.pdf',
            $documentType === 'return_receipt' ? 'return-receipt' : 'rental-agreement',
            $reservation->reservation_number,
            now()->format('Ymd\THis').'-'.substr($sha, 0, 6)
        );
        $path = "reservations/{$reservation->id}/revisions/{$filename}";
        Storage::disk('public')->put($path, $binary);

        $prev = AgreementRevision::where('reservation_id', $reservation->id)
            ->where('document_type', $documentType)
            ->latest('id')->first();

        $revision = AgreementRevision::create([
            'reservation_id' => $reservation->id,
            'document_type'  => $documentType,
            'action'         => $action,
            'pdf_path'       => $path,
            'sha256'         => $sha,
            'prev_sha256'    => $prev?->sha256,
            'snapshot'       => [
                'reservation' => $reservation->only(['id','reservation_number','status','pickup_date','return_date',
                    'actual_pickup_date','actual_return_date','daily_rate','total_days','subtotal','tax_amount',
                    'addons_total','total_price','total_paid','outstanding_balance','security_deposit',
                    'odometer_out','odometer_in','fuel_out','fuel_in',
                    'insurance_source','insurance_company_seen','insurance_policy_seen',
                ]),
                'customer' => $reservation->customer?->only(['id','first_name','last_name','phone','email',
                    'address','city','state','zip','drivers_license_number','dl_state','dl_expiration','date_of_birth']),
                'vehicle' => $reservation->vehicle?->only(['id','year','make','model','vin','license_plate','vehicle_class']),
                'active_hold' => $reservation->activeHold?->only(['id','amount','card_brand','card_last4','card_exp','status']),
            ],
            'created_by' => Auth::id(),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);

        // On the reservation itself, keep pointer to the latest rental agreement PDF
        if ($documentType === 'rental_agreement') {
            $reservation->update(['lease_agreement_path' => $path]);
        }

        return $revision;
    }
}
