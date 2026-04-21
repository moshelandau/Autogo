<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rental Agreement #{{ $reservation->reservation_number }}</title>
<style>
@page { margin: 24mm 18mm; }
* { box-sizing: border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #111; line-height: 1.45; }
h1 { font-size: 22px; margin: 0 0 4px; text-align: center; letter-spacing: 1px; }
h2 { font-size: 14px; margin: 18px 0 6px; padding: 6px 8px; background: #1e293b; color: #fff; border-radius: 3px; }
h3 { font-size: 12px; margin: 10px 0 4px; color: #1e293b; }
.muted { color: #64748b; font-size: 10px; }
.center { text-align: center; }
.bold { font-weight: bold; }
.box { border: 1px solid #e5e7eb; padding: 10px; border-radius: 4px; margin: 6px 0; }
.warn { border: 2px solid #b91c1c; background: #fef2f2; padding: 12px; border-radius: 4px; }
.warn h3 { color: #991b1b; margin-top: 0; }
table.kv { width: 100%; border-collapse: collapse; }
table.kv td { padding: 4px 6px; vertical-align: top; }
table.kv td.k { width: 28%; color: #475569; font-weight: bold; font-size: 10px; text-transform: uppercase; }
table.cols { width: 100%; border-collapse: collapse; }
table.cols td { vertical-align: top; padding: 0 8px; width: 50%; }
.terms p { margin: 6px 0; }
.terms ol { padding-left: 18px; }
.terms li { margin: 4px 0; }
.signature-block { margin-top: 30px; }
.signature-line { border-top: 1px solid #111; margin-top: 60px; padding-top: 4px; font-size: 10px; }
.sig-img { max-height: 80px; max-width: 320px; }
.beis-din { background: #fffbeb; border: 1px solid #fbbf24; padding: 10px; border-radius: 4px; margin-top: 12px; font-size: 10px; }
</style>
</head>
<body>

<div class="center">
    <h1>HIGH CAR RENTAL — RENTAL AGREEMENT</h1>
    <div class="muted">Agreement #{{ $reservation->reservation_number }} · Generated {{ now()->format('m/d/Y h:i A') }}</div>
</div>

<table class="cols">
<tr>
    <td>
        <h3>RENTER</h3>
        <table class="kv">
            <tr><td class="k">Name</td><td>{{ $customer->first_name }} {{ $customer->last_name }}</td></tr>
            <tr><td class="k">Phone</td><td>{{ $customer->phone ?: '—' }}</td></tr>
            <tr><td class="k">Address</td><td>{{ $customer->address }} {{ $customer->city }} {{ $customer->state }} {{ $customer->zip }}</td></tr>
            <tr><td class="k">DL #</td><td>{{ $customer->drivers_license_number ?: '—' }} ({{ $customer->dl_state ?: '?' }})</td></tr>
            <tr><td class="k">DL Exp</td><td>{{ optional($customer->dl_expiration)->format('m/d/Y') }}</td></tr>
            <tr><td class="k">DOB</td><td>{{ optional($customer->date_of_birth)->format('m/d/Y') }}</td></tr>
        </table>
    </td>
    <td>
        <h3>VEHICLE & RENTAL TERMS</h3>
        <table class="kv">
            <tr><td class="k">Vehicle</td><td>{{ optional($reservation->vehicle)->year }} {{ optional($reservation->vehicle)->make }} {{ optional($reservation->vehicle)->model }}</td></tr>
            <tr><td class="k">VIN</td><td>{{ optional($reservation->vehicle)->vin ?: '—' }}</td></tr>
            <tr><td class="k">Plate</td><td>{{ optional($reservation->vehicle)->license_plate ?: '—' }}</td></tr>
            <tr><td class="k">Pickup</td><td>{{ optional($reservation->pickup_date)->format('m/d/Y h:i A') }}</td></tr>
            <tr><td class="k">Return</td><td>{{ optional($reservation->return_date)->format('m/d/Y h:i A') }}</td></tr>
            <tr><td class="k">Daily Rate</td><td>${{ number_format((float)$reservation->daily_rate, 2) }}</td></tr>
            <tr><td class="k">Days</td><td>{{ $reservation->total_days }}</td></tr>
            <tr><td class="k">Total</td><td><strong>${{ number_format((float)$reservation->total_price, 2) }}</strong></td></tr>
        </table>
    </td>
</tr>
</table>

<h3>INSURANCE</h3>
<div class="box">
    @if ($reservation->insurance_source === 'own_policy')
        Renter is using their own auto insurance policy:
        <strong>{{ $reservation->insurance_company_seen ?: $customer->insurance_company }}</strong>
        — Policy #<strong>{{ $reservation->insurance_policy_seen ?: $customer->insurance_policy }}</strong>
    @elseif ($reservation->insurance_source === 'credit_card')
        Renter is using credit-card-provided rental insurance.
    @else
        ⚠️ NO INSURANCE ON FILE — renter accepts FULL personal liability for any damage, theft, or third-party claims.
    @endif
</div>

<h3>SECURITY DEPOSIT</h3>
<div class="box">
    A security hold of <strong>${{ number_format((float)($activeHold->amount ?? 250), 2) }}</strong>
    has been authorized on
    @if ($activeHold)
        {{ strtoupper($activeHold->card_brand ?? 'card') }} ending in {{ $activeHold->card_last4 }}.
    @else
        the renter's payment method on file.
    @endif
    The hold is retained for thirty (30) days post-return and may be captured if damage is discovered or charges remain unpaid.
</div>

<h2>TERMS AND CONDITIONS</h2>
<div class="terms">

<h3>1. FULL PERSONAL RESPONSIBILITY FOR DAMAGE</h3>
<p>The renter (and any authorized driver) is <strong>personally and fully responsible</strong> for any and all damage, loss, theft, vandalism, mechanical damage, interior damage, or diminished value to the vehicle that occurs from the moment of pickup until the vehicle is physically returned to and inspected at HIGH CAR RENTAL's location and accepted in writing by an authorized representative. This responsibility applies <strong>regardless of fault</strong>, including damage caused by third parties, weather events, vandalism, and unknown causes.</p>

<h3>2. EXCLUSIVE REPAIR RIGHTS</h3>
<p>If the vehicle is damaged, <strong>the renter is prohibited from taking the vehicle to any other mechanic, body shop, or repair facility</strong> for any reason. All inspections, estimates, and repairs must be performed exclusively by HIGH CAR RENTAL or a body shop designated by HIGH CAR RENTAL. Any violation of this clause makes the renter liable for the full cost of any duplicate inspection, lost rental income from delays, diminished value, and all attorney's fees and costs HIGH CAR RENTAL incurs to enforce this provision.</p>

<h3>3. INSURANCE PROCEEDS BELONG TO HIGH CAR RENTAL</h3>
<p>The renter agrees that <strong>any and all insurance payments</strong>, including but not limited to property damage, comprehensive, collision, loss-of-use, rental reimbursement, diminished value, and any third-party recoveries that arise from the rental of this vehicle, <strong>are the property of HIGH CAR RENTAL alone</strong> and are intended to compensate HIGH CAR RENTAL for damage to the vehicle and lost rental income. The renter assigns and authorizes the direct payment of all such proceeds to HIGH CAR RENTAL and shall sign any document needed to effect that direct payment.</p>

<h3>4. RENTER'S OBLIGATION FOR ALL LOSSES</h3>
<p>If the renter or any party acting on the renter's behalf violates any of the above provisions — including but not limited to taking the vehicle to a third-party mechanic, intercepting insurance proceeds, delaying inspection, refusing to provide insurance information, or making the vehicle unavailable — <strong>the renter is required to pay HIGH CAR RENTAL for ALL losses</strong>, including but not limited to: full repair cost, diminished value, lost rental income at the daily rate for every day the vehicle is unavailable, towing, storage, attorney fees, court costs, and collection costs.</p>

<h3>5. RETURN, FUEL, MILEAGE, AND CONDITION</h3>
<p>Vehicle must be returned at the agreed time and location, with the same fuel level as at pickup, no unrepaired interior damage (smoking, stains, odors), and no unauthorized modifications. Late returns are billed at the daily rate. Excess mileage (if applicable) is billed at the agreed per-mile rate. The renter shall not allow any person not listed as an authorized driver to operate the vehicle.</p>

<h3>6. PROHIBITED USES</h3>
<p>Renter shall not: drive while intoxicated or impaired; drive outside the United States or Canada without written consent; use the vehicle for racing, towing, off-road, ride-share/livery, or any illegal purpose; transport hazardous materials; sublet or rent the vehicle to others.</p>

<h3>7. PAYMENT AND COLLECTIONS</h3>
<p>All charges, including damage and toll/violation pass-throughs, are payable on demand. The renter authorizes HIGH CAR RENTAL to charge the credit card on file for any unpaid charges. Disputed chargebacks shall not relieve the renter of payment obligations. Past-due balances accrue interest at the maximum rate permitted by law.</p>

<h3>8. INDEMNIFICATION</h3>
<p>The renter agrees to defend, indemnify, and hold harmless HIGH CAR RENTAL, its owners, employees, and agents from and against any and all claims, damages, fines, losses, costs, and expenses (including attorneys' fees) arising out of the renter's use, possession, or operation of the vehicle.</p>

<div class="beis-din">
<strong>9. DISPUTE RESOLUTION — BEIS DIN.</strong>
Both parties agree that any and all disputes arising under or relating to this agreement (including its formation, performance, breach, damages, and enforcement) shall be resolved exclusively before a duly constituted Beis Din (Rabbinical Court) mutually agreed upon by the parties; and in the absence of agreement, before the Beis Din Maysharim of Monsey, New York. Both parties accept the Beis Din's jurisdiction, waive any right to bring the dispute in any civil or secular court, and agree that the Beis Din's ruling shall be final and binding and may be enforced as an arbitration award in any court of competent jurisdiction. The prevailing party is entitled to all costs and to the reasonable attorney's, advocate's, or to'en's fees incurred in enforcing this agreement.
</div>

<h3>10. ENTIRE AGREEMENT</h3>
<p>This document constitutes the entire agreement between the parties and supersedes all prior representations or agreements. No oral statement modifies these terms. If any provision is found unenforceable, the remainder shall remain in full effect.</p>

</div>

<div class="signature-block">
    <h2>SIGNATURE</h2>
    <p>By signing below, the renter acknowledges they have <strong>read, understood, and agreed</strong> to every term above, including the Beis Din clause and full personal responsibility for any damage.</p>

    <table style="width: 100%; margin-top: 18px;">
    <tr>
        <td style="width: 60%;">
            @if (!empty($signatureDataUrl))
                <img src="{{ $signatureDataUrl }}" class="sig-img" alt="Signature" />
            @endif
            <div class="signature-line">
                Renter Signature — {{ $customer->first_name }} {{ $customer->last_name }}
            </div>
        </td>
        <td style="width: 40%;">
            <div class="signature-line">
                Date: {{ now()->format('m/d/Y') }}
            </div>
        </td>
    </tr>
    </table>
</div>

</body>
</html>
