<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RentalAgreementController extends Controller
{
    public function generate(Request $request, Reservation $reservation)
    {
        $request->validate([
            'signature_data_url' => 'nullable|string',
            'download'           => 'nullable|boolean',
        ]);

        $reservation->load(['customer', 'vehicle', 'activeHold']);

        $pdf = Pdf::loadView('rental.agreement', [
            'reservation'      => $reservation,
            'customer'         => $reservation->customer,
            'activeHold'       => $reservation->activeHold,
            'signatureDataUrl' => $request->input('signature_data_url'),
        ])->setPaper('letter', 'portrait');

        // Save to storage and link on reservation
        $name = "rental-agreement-{$reservation->reservation_number}.pdf";
        $path = "reservations/{$reservation->id}/{$name}";
        Storage::disk('public')->put($path, $pdf->output());
        $reservation->update(['lease_agreement_path' => $path]);

        if ($request->boolean('download')) {
            return $pdf->download($name);
        }

        return back()->with('success', 'Rental agreement PDF generated.');
    }

    public function preview(Request $request, Reservation $reservation)
    {
        $reservation->load(['customer', 'vehicle', 'activeHold']);
        return Pdf::loadView('rental.agreement', [
            'reservation'      => $reservation,
            'customer'         => $reservation->customer,
            'activeHold'       => $reservation->activeHold,
            'signatureDataUrl' => null,
        ])->setPaper('letter', 'portrait')->stream("agreement-preview-{$reservation->reservation_number}.pdf");
    }
}
