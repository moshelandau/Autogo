<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rental Agreement #{{ $reservation->reservation_number }}</title>
<style>
@page { margin: 14mm 12mm; }
* { box-sizing: border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5px; color: #111; line-height: 1.4; }
.header { text-align: center; position: relative; padding-bottom: 4px; }
.brand { position: absolute; left: 0; top: 0; font-weight: bold; font-size: 18px; color: #0066cc; letter-spacing: -0.5px; }
.brand small { color: #111; font-weight: normal; letter-spacing: 0; }
.title { font-size: 22px; margin: 2px 0; font-weight: bold; }
.contact { font-size: 10px; line-height: 1.4; }
.status-box { position: absolute; right: 0; top: 0; border: 1px solid #ddd; padding: 4px 6px; border-radius: 3px; font-size: 8px; max-width: 160px; text-align: left; }
.status-box .ra { font-weight: bold; }
.status-box .msg { color: #555; font-style: italic; }

table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
table.grid td { vertical-align: top; padding: 6px; border: 1px solid #ddd; }
.section-h { background: #1e293b; color: white; font-weight: bold; text-align: center; padding: 6px; font-size: 11px; }
.kv { font-size: 9.5px; }
.kv strong { display: inline-block; min-width: 110px; }
.charge-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dashed #eee; }
.charge-row.total { border-top: 2px solid #111; border-bottom: 2px solid #111; font-weight: bold; padding: 4px 0; margin-top: 4px; }
.charge-row.outstanding { color: #c00; font-weight: bold; font-style: italic; }

.terms { font-size: 8.5px; line-height: 1.4; margin-top: 10px; }
.terms h2 { text-align: center; font-size: 12px; background: #1e293b; color: white; padding: 4px; margin: 10px 0 6px; font-weight: bold; letter-spacing: 0.5px; }
.terms h3 { font-size: 9.5px; margin: 6px 0 2px; font-weight: bold; }
.terms p { margin: 4px 0; text-align: justify; }
.terms .bullet { margin: 3px 0; }
.terms .highlight { background: #fffbea; border-left: 3px solid #b45309; padding: 4px 8px; margin: 4px 0; font-weight: bold; }
.terms .beis-din { background: #fef2f2; border: 1px solid #dc2626; padding: 6px 8px; margin: 4px 0; font-size: 8.5px; }

.signature-block { margin-top: 30px; padding-top: 10px; border-top: 2px solid #111; }
.signature-block table { width: 100%; }
.signature-block td { padding: 4px; vertical-align: bottom; }
.sig-img { max-height: 60px; max-width: 320px; display: block; }
.sig-line { border-bottom: 1px solid #111; min-height: 50px; position: relative; }
.sig-meta { font-size: 7.5px; color: #888; margin-top: 2px; }

.carimg { text-align: center; padding: 4px; }
.carimg svg { max-width: 90%; height: auto; }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="brand">High<small>RENTAL</small></div>
    <div class="status-box">
        <div class="ra">RA#{{ $reservation->reservation_number }}</div>
        @if(!empty($reservation->status_note))
            <div class="msg">{{ $reservation->status_note }}</div>
        @else
            <div class="msg">{{ ucfirst($reservation->status) }}</div>
        @endif
    </div>
    <div class="title">Rental Agreement</div>
    <div class="contact">
        Office@autogoco.com<br>
        845-500-6901<br>
        18 Hamburg Way #002 Monroe NY 10950
    </div>
</div>

<!-- RENTER + RENTAL INFO -->
<table class="grid">
    <tr>
        <td class="section-h" style="width:50%">Renter Information</td>
        <td class="section-h" style="width:50%">Rental Information</td>
    </tr>
    <tr>
        <td class="kv">
            <div><strong>Renter:</strong> {{ $customer->first_name }} {{ $customer->last_name }}</div>
            <div><strong>DL#:</strong> {{ $customer->drivers_license_number ?: '—' }}</div>
            <div><strong>Email:</strong> {{ $customer->email ?: '—' }}</div>
            <div><strong>Address:</strong> {{ trim(($customer->address ?? '').' '.($customer->address_2 ?? '')) }}, {{ $customer->city }} {{ $customer->state }} {{ $customer->zip }}</div>
            <div><strong>DOB:</strong> {{ optional($customer->date_of_birth)->format('m/d/Y') ?: '—' }}</div>
            <div><strong>EXP:</strong> {{ optional($customer->dl_expiration)->format('m/d/Y') ?: '—' }}</div>
            <div><strong>Phone:</strong> {{ $customer->phone ?: '—' }}</div>
            <div><strong>ZIP Code:</strong> {{ $customer->zip ?: '—' }}</div>

            @if ($activeHold)
            <div style="margin-top:6px; padding-top:4px; border-top: 1px dashed #ccc;">
                <strong>Credit Card Type:</strong> {{ strtoupper($activeHold->card_brand ?? '') }}<br>
                <strong>Last 4 Digits of CC:</strong> {{ $activeHold->card_last4 ?? '—' }}<br>
                <strong>CC Exp.:</strong> {{ $activeHold->card_exp ?? '—' }}
            </div>
            @endif
        </td>
        <td class="kv">
            <div><strong>Date out:</strong> {{ optional($reservation->actual_pickup_date ?? $reservation->pickup_date)->format('m/d/Y') }}</div>
            <div><strong>Date due:</strong> {{ optional($reservation->return_date)->format('m/d/Y') }}</div>
            <div><strong>Pickup Location:</strong> {{ optional($reservation->pickupLocation)->name ?: '—' }}</div>
            <div><strong>Return Location:</strong> {{ optional($reservation->returnLocation ?? $reservation->pickupLocation)->name ?: '—' }}</div>
            <div><strong>Time:</strong> {{ optional($reservation->actual_pickup_date ?? $reservation->pickup_date)->format('h:i A') }}</div>
            <div><strong>Time in:</strong> {{ optional($reservation->return_date)->format('h:i A') }}</div>
            <div style="margin-top:4px;"><strong>Insurance:</strong>
                @if($reservation->insurance_source === 'own_policy') Own policy —
                    <em>{{ $reservation->insurance_company_seen ?: ($customer->insurance_company ?? '?') }} #{{ $reservation->insurance_policy_seen ?: ($customer->insurance_policy ?? '?') }}</em>
                @elseif($reservation->insurance_source === 'credit_card') Credit card coverage
                @else <strong style="color:#c00">⚠ NONE — renter accepts full personal liability</strong>
                @endif
            </div>
        </td>
    </tr>
    <tr>
        <td class="section-h">Vehicle Information</td>
        <td class="section-h">Charge Information</td>
    </tr>
    <tr>
        <td class="kv">
            @if($reservation->vehicle)
                <div><strong>Plate:</strong> {{ $reservation->vehicle->license_plate }}</div>
                <div><strong>Brand:</strong> {{ $reservation->vehicle->make }}</div>
                <div><strong>Vehicle class:</strong> {{ $reservation->vehicle_class ?: $reservation->vehicle->vehicle_class }}</div>
                <div><strong>Model:</strong> {{ $reservation->vehicle->model }}</div>
                <div><strong>Year:</strong> {{ $reservation->vehicle->year }}</div>
                <div><strong>VIN:</strong> {{ $reservation->vehicle->vin ?: '—' }}</div>
                <div><strong>Color:</strong> {{ $reservation->vehicle->color ?: '—' }}</div>
                <div><strong>Odometer Out:</strong> {{ $reservation->odometer_out ?: '—' }}</div>
                <div><strong>Fuel Out:</strong> {{ $reservation->fuel_out ?: '—' }}</div>
            @else
                <em>Class: {{ $reservation->vehicle_class ?: 'Any' }} (vehicle not yet assigned)</em>
            @endif

            <div class="carimg">
                <svg viewBox="0 0 200 80" xmlns="http://www.w3.org/2000/svg">
                    <!-- Stylized car top view -->
                    <rect x="40" y="15" width="120" height="50" rx="14" ry="14" fill="none" stroke="#333" stroke-width="1.5"/>
                    <rect x="60" y="22" width="80" height="20" fill="none" stroke="#333" stroke-width="1"/>
                    <line x1="100" y1="22" x2="100" y2="42" stroke="#333" stroke-width="1"/>
                    <circle cx="50" cy="22" r="5" fill="#1e293b"/>
                    <circle cx="50" cy="58" r="5" fill="#1e293b"/>
                    <circle cx="150" cy="22" r="5" fill="#1e293b"/>
                    <circle cx="150" cy="58" r="5" fill="#1e293b"/>
                </svg>
            </div>
        </td>
        <td class="kv">
            <div class="charge-row">
                <span>{{ $reservation->total_days }} × day @ ${{ number_format((float)$reservation->daily_rate, 2) }}</span>
                <span>${{ number_format((float)$reservation->subtotal, 2) }}</span>
            </div>
            @if (str_contains((string)$reservation->notes, '[Weekend $20 credit applied]'))
            <div class="charge-row" style="color:#059669">
                <span>Weekend Credit (Fri–Mon)</span>
                <span>−$20.00</span>
            </div>
            @elseif ($reservation->discount_amount > 0)
            <div class="charge-row" style="color:#059669">
                <span>Discount</span>
                <span>−${{ number_format((float)$reservation->discount_amount, 2) }}</span>
            </div>
            @endif
            <div class="charge-row">
                <span>Sales Tax</span>
                <span>${{ number_format((float)$reservation->tax_amount, 2) }}</span>
            </div>
            @if ($reservation->addons_total > 0)
            <div class="charge-row">
                <span>Add-ons</span>
                <span>${{ number_format((float)$reservation->addons_total, 2) }}</span>
            </div>
            @endif
            <div class="charge-row total">
                <span>TOTAL</span>
                <span>${{ number_format((float)$reservation->total_price, 2) }}</span>
            </div>
            <div class="charge-row">
                <span>Paid</span>
                <span style="color:#059669">${{ number_format((float)$reservation->total_paid, 2) }}</span>
            </div>
            <div class="charge-row outstanding">
                <span>Amount Outstanding</span>
                <span>${{ number_format((float)$reservation->outstanding_balance, 2) }}</span>
            </div>
            <div class="charge-row" style="margin-top:4px; border-top:1px dashed #ccc; padding-top:4px;">
                <span>Security Deposit (30-day hold)</span>
                <span>${{ number_format((float)($activeHold->amount ?? 250), 2) }}</span>
            </div>
        </td>
    </tr>
</table>

<!-- TERMS -->
<div class="terms">
<h2>Terms & Conditions</h2>

<p><strong>Definitions.</strong> "Agreement" means all terms and conditions in the rental record ("Rental Record") and any additional documents you sign or we provide at the time of rental, electronically or otherwise. "Renter" means each person signing this Agreement, each Authorized Driver, and every person or organization to whom charges are billed by us at its or the Renter's direction. "We," "our" or "us" means <strong>High Car Rental</strong> (and, where applicable, related entities including Auto Go). "Authorized Driver" means (a) the Renter; (b) any additional driver listed by us on this Agreement; and (c) any other person defined as an "authorized driver" under applicable law. Each Authorized Driver must have a valid operator's license and be at least age 21 (unless otherwise specified in applicable law). "Vehicle" means the automobile or truck identified in this Agreement and any vehicle we substitute for it, and all its tires, tools, accessories, equipment, keys and documents provided inside the vehicle at the time of rental. "Physical Damage" means damage to or loss of the Vehicle resulting from (but not limited to) collision, theft, vandalism, acts of nature, riots or other civil disturbances, hail, flood, fire or any other loss not caused by collision. "Loss of Use" means the loss of our ability to use the Vehicle for our purposes because of Vehicle damage or loss, including use for rent, display for rent and/or sale, the opportunity to upgrade or sell, or transportation of employees. "Diminution of Value" means the difference between the fair market value of the Vehicle before damage or loss and its value after repairs as calculated by a third-party estimate obtained by us or on our behalf.</p>

<h3>1. Rental; Indemnity; Personal Property; Warranties</h3>
<p>Only Authorized Drivers may use the Vehicle. We may repossess the Vehicle at your expense without notice if abandoned or used in violation of law or of this Agreement. You agree to indemnify, defend and hold us harmless from all judgments, claims, liability, costs and attorney fees we incur arising out of this rental and your use of the Vehicle. You release us, our agents and employees from all claims for loss of or damage to your personal property or that of another person that we received, handled or stored, or that was left or carried in or on the Vehicle. We make no warranties, express, implied or apparent, regarding the Vehicle.</p>

<h3>2. Condition and Return of Vehicle</h3>
<p>The rental of this vehicle constitutes a "bailment." The Vehicle must be returned on the date and time noted and in the same condition received, except for ordinary wear. Our determination of the condition of the Vehicle is subject to a final inspection for damage(s) which may occur in our facilities after drop off, whether or not the vehicle is checked in by an employee and whether or not such damage(s) are immediately recognizable or hidden. If the Vehicle is returned after closing hours, Renter's responsibility for damages continues until the final inspection even if the damage occurred after the vehicle was returned.</p>

<h3>3. Responsibility for Damage or Loss</h3>
<p><strong>Regardless of fault, you are responsible for all damage to, loss of, or theft of the Vehicle during the rental period resulting from any cause</strong>, including: (a) physical damage caused by collisions, weather, vandalism, road conditions, acts of nature, or any other cause; (b) if we determine the Vehicle is a total loss, the full fair retail market value less salvage; (c) if repairable, the difference between the Vehicle's value before and after damage OR the reasonable estimated retail value or actual cost of repair plus Diminution of Value; (d) Loss of Use, calculated by multiplying the daily rental rate by the actual or estimated days from damage until replaced or repaired — payable regardless of fleet utilization and whether we had other vehicles to rent; (e) an administrative fee based on the damage.</p>

<div class="highlight">
Our policy for CC insurance coverage: <strong>the customer pays for damages in advance</strong>; when the CC company sends reimbursement they send it directly to the customer. If the CC company refuses to pay for any reason, the customer takes full responsibility for the payment. If the customer does not pay within <strong>24 hours after return</strong>, we will charge the card on file.
</div>

<p><strong>Exclusive Repair Rights.</strong> If the Vehicle is damaged, Renter is <strong>prohibited from taking the Vehicle to any other mechanic, body shop, or repair facility</strong>. All inspections, estimates and repairs must be performed exclusively by High Car Rental or a body shop we designate. Violation makes Renter liable for all losses including duplicate inspection costs, lost rental income, diminished value, attorney's fees.</p>

<p><strong>Insurance Proceeds.</strong> Renter agrees that any and all insurance proceeds — property damage, comprehensive, collision, loss-of-use, rental reimbursement, diminished value and third-party recoveries — arising from the rental of this vehicle are the property of High Car Rental alone, are intended to compensate High Car Rental for vehicle damage and lost rental income, and Renter assigns and authorizes direct payment of all such proceeds to High Car Rental.</p>

<h3>4. Prohibited Uses & Fees</h3>
<p>The Vehicle shall not be used: (a) by anyone not an Authorized Driver or with a suspended license; (b) under the influence of drugs or alcohol; (c) by anyone who obtained it through fraud; (d) in furtherance of any illegal purpose or felony; (e) to carry persons or property for hire; (f) to push or tow anything; (g) in any race or contest; (h) to teach anyone to drive; (i) to carry hazardous items; (j) outside the United States or Canada without written consent; (k) on unpaved roads; (l) to transport more persons than seatbelts; (m) without approved child safety seats; (n) with tampered odometer; (o) when further operation would damage the Vehicle; (p) with inadequately secured cargo; (q) by anyone sending/reading texts or emails while driving. <strong>Smoking in the Vehicle is prohibited.</strong></p>

<div class="highlight">
<strong>Fee schedule (automatic charges to card on file):</strong><br>
• <strong>Smoking:</strong> $50 extra if any sign of smoking is found.<br>
• <strong>EZPass use:</strong> $10 per use in addition to the toll. Cash not accepted for EZPass — card on file is charged automatically without further consent.<br>
• <strong>Mileage:</strong> If driving long distances, estimate your mileage when reserving; otherwise an extra mileage fee is assessed.<br>
• <strong>After-hours pickup/return:</strong> $20 service fee.<br>
• The customer consents to charge all additional fees on this rental agreement to the card on file.<br>
• <strong>All CC transactions are charged under the name "High Rental" (or a related entity — for example "Auto Go").</strong>
</div>

<div class="highlight">
<strong>Business hours:</strong><br>
• <strong>Monsey:</strong> Sunday – Thursday 9:00 AM – 6:00 PM · Friday 9:00 AM – 12:30 PM.<br>
• <strong>Monroe:</strong> Monday – Thursday 9:00 AM – 6:00 PM · Friday 9:00 AM – 12:30 PM.
</div>

<div class="highlight">
<strong>Weekend credit:</strong> Rentals that cover Friday, Saturday and Sunday (weekend block) automatically receive a <strong>$20 credit</strong> on the total.
</div>

<h3>5. Insurance</h3>
<p>If you purchase Insurance through us, subject to the terms of this Agreement, we will waive our right to hold you financially responsible for the portion of physical damage noted on the Rental Record, including loss-of-use and administrative fees.</p>

<h3>6. Responsibility to Others; Handling Accidents/Incidents</h3>
<p>You are responsible for all injury, damage, or loss you cause to yourself and others (including passengers). We are not responsible for injury or damage you cause to others unless required by law. Your liability insurance coverage must meet the minimum limits required by the state where the loss occurs. You must (a) report all damage and accidents to us and the police as soon as safe; (b) complete our incident report form; (c) provide us with a legible copy of any service of process, pleading or notice related to an accident or incident involving the Vehicle. Failure to report is a material breach and may invalidate coverage. <strong>The Vehicle may not be taken into Mexico under any circumstances.</strong></p>

<h3>7. Payment; Charges</h3>
<p>You permit us to reserve against your payment card a reasonable amount in addition to estimated total charges. We may use the reserve to pay Charges. You will pay all Charges at or before the conclusion of this rental or on demand: (a) time charges; (b) mileage; (c) optional product fees; (d) fuel and refueling fees; (e) taxes and surcharges; (f) recovery expenses; (g) attorney fees; (h) cleaning fee; (i) towing/storage/court costs; (j) late return charges; (k) replacement cost of lost parts. All Charges are subject to final audit; errors are corrected with your payment card.</p>

<h3>8. Responsibility for Tolls, Traffic Violations, and Other Charges</h3>
<p>You are responsible for all tolls, parking citations, photo enforcement, toll evasion fines, and other fines, fees and penalties during this rental. If we are notified that we may be responsible, you authorize us to release your information to charging authorities and to charge all such payments plus an administrative fee to the payment card on file.</p>

<h3>9. Personal Information; Communications</h3>
<p>We may disclose personally identifiable information to law enforcement or other third parties in enforcing our rights. You agree that we or our assigns may contact you by telephone (including cell), text message and email to service the account, recover amounts owed, or for service messages — including pre-recorded/artificial voice and auto-dialer technology.</p>

<h3>10. Dispute Resolution — Beis Din</h3>
<div class="beis-din">
Both parties agree that any and all disputes arising under or relating to this agreement (including its formation, performance, breach, damages and enforcement) shall be resolved <strong>exclusively before a duly constituted Beis Din (Rabbinical Court)</strong> mutually agreed upon by the parties; and in the absence of agreement, before the <strong>Beis Din Maysharim of Monsey, New York</strong>. Both parties accept the Beis Din's jurisdiction, <strong>waive any right to bring the dispute in any civil or secular court</strong>, and agree that the Beis Din's ruling shall be final and binding and may be enforced as an arbitration award in any court of competent jurisdiction. The prevailing party is entitled to all costs and to the reasonable attorney's, advocate's, or to'en's fees incurred in enforcing this agreement.
</div>

<h3>11. Miscellaneous</h3>
<p>No term of this Agreement may be waived or modified except by a writing we have signed. This Agreement constitutes the entire agreement between the parties. Our acceptance of payment or failure to exercise any right does not waive any other provision. This Agreement is governed by the substantive law of the jurisdiction where the rental commences (subject to the Beis Din clause above). If any provision is deemed void or unenforceable, the remainder stays valid. <strong>YOU AND WE EACH IRREVOCABLY WAIVE ALL RIGHTS TO TRIAL BY JURY</strong> in any legal proceeding arising out of this Agreement.</p>

</div>

<!-- SIGNATURE -->
<div class="signature-block">
    <table>
    <tr>
        <td style="width:60%">
            @if(!empty($signatureDataUrl))
                <img class="sig-img" src="{{ $signatureDataUrl }}" alt="Signature" />
            @else
                <div class="sig-line"></div>
            @endif
            <div><strong>Renter Signature — {{ $customer->first_name }} {{ $customer->last_name }}</strong></div>
            @if (!empty($signature))
                <div class="sig-meta">
                    Signed {{ optional($signature->signed_at)->format('m/d/Y h:i A') }} ·
                    IP: {{ $signature->ip_address }} ·
                    SHA256: {{ substr($signature->sha256, 0, 16) }}…
                </div>
            @endif
        </td>
        <td style="width:40%; vertical-align:top">
            <div><strong>Date:</strong> {{ now()->format('m/d/Y') }}</div>
            <div style="margin-top:4px"><strong>Agreement ID:</strong> RA#{{ $reservation->reservation_number }}</div>
            @if (!empty($action))
            <div style="margin-top:2px; font-size:8px; color:#555">Event: {{ str_replace('_', ' ', $action) }}</div>
            @endif
        </td>
    </tr>
    </table>
</div>

</body>
</html>
