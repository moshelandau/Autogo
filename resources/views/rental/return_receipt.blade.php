<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>Return Receipt #{{ $reservation->reservation_number }}</title>
<style>
@page { margin: 16mm 14mm; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; line-height: 1.45; }
.title { font-size: 22px; font-weight: bold; text-align: center; }
.brand { text-align: center; font-size: 16px; color: #0066cc; font-weight: bold; margin-bottom: 2px; }
.contact { text-align: center; font-size: 9px; color: #555; margin-bottom: 10px; }
table.grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
table.grid td { vertical-align: top; padding: 6px; border: 1px solid #ddd; font-size: 9.5px; }
.section-h { background: #065f46; color: white; font-weight: bold; text-align: center; padding: 6px; font-size: 11px; }
.kv strong { display: inline-block; min-width: 120px; }
.charge-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dashed #eee; }
.charge-row.total { border-top: 2px solid #111; border-bottom: 2px solid #111; font-weight: bold; padding: 4px 0; margin-top: 4px; }
.charge-row.refund { color: #059669; font-weight: bold; }
.stamp { border: 2px solid #065f46; color: #065f46; font-weight: bold; padding: 4px 8px; display: inline-block; transform: rotate(-3deg); font-size: 14px; letter-spacing: 2px; }
.signature-block { margin-top: 30px; padding-top: 10px; border-top: 2px solid #111; }
.sig-img { max-height: 60px; }
.sig-meta { font-size: 7.5px; color: #888; }
.note { background: #fef3c7; border-left: 3px solid #b45309; padding: 6px 8px; margin: 6px 0; font-size: 9px; }
</style>
</head>
<body>

<div class="brand">High<span style="color:#111">RENTAL</span></div>
<div class="contact">Office@autogoco.com · 845-500-6901 · 18 Hamburg Way #002 Monroe NY 10950</div>
<div class="title">Return Receipt</div>
<div style="text-align:center; margin: 4px 0 10px;">
    <span class="stamp">✓ RETURNED</span>
</div>

<table class="grid">
    <tr>
        <td class="section-h" style="width:50%">Rental Summary</td>
        <td class="section-h" style="width:50%">Charges & Payments</td>
    </tr>
    <tr>
        <td class="kv">
            <div><strong>RA#:</strong> {{ $reservation->reservation_number }}</div>
            <div><strong>Renter:</strong> {{ $customer->first_name }} {{ $customer->last_name }}</div>
            <div><strong>Phone:</strong> {{ $customer->phone ?: '—' }}</div>
            <div><strong>Vehicle:</strong> {{ optional($reservation->vehicle)->year }} {{ optional($reservation->vehicle)->make }} {{ optional($reservation->vehicle)->model }} · {{ optional($reservation->vehicle)->license_plate }}</div>
            <div><strong>Pickup:</strong> {{ optional($reservation->actual_pickup_date ?? $reservation->pickup_date)->format('m/d/Y h:i A') }}</div>
            <div><strong>Return:</strong> {{ optional($reservation->actual_return_date ?? $reservation->return_date)->format('m/d/Y h:i A') }}</div>
            <div><strong>Odometer Out:</strong> {{ $reservation->odometer_out ?? '—' }}</div>
            <div><strong>Odometer In:</strong> {{ $reservation->odometer_in ?? '—' }}</div>
            <div><strong>Total Miles:</strong>
                @php $miles = ($reservation->odometer_in && $reservation->odometer_out) ? ($reservation->odometer_in - $reservation->odometer_out) : null; @endphp
                {{ $miles !== null ? number_format($miles) . ' mi' : '—' }}
            </div>
            <div><strong>Fuel Out / In:</strong> {{ $reservation->fuel_out ?: '—' }} / {{ $reservation->fuel_in ?: '—' }}</div>
        </td>
        <td class="kv">
            <div class="charge-row">
                <span>{{ $reservation->total_days }} × day @ ${{ number_format((float)$reservation->daily_rate, 2) }}</span>
                <span>${{ number_format((float)$reservation->subtotal, 2) }}</span>
            </div>
            @if (str_contains((string)$reservation->notes, '[Weekend $20 credit applied]'))
                <div class="charge-row" style="color:#059669"><span>Weekend Credit (Fri–Mon)</span><span>−$20.00</span></div>
            @elseif ($reservation->discount_amount > 0)
                <div class="charge-row" style="color:#059669"><span>Discount</span><span>−${{ number_format((float)$reservation->discount_amount, 2) }}</span></div>
            @endif
            @if ($reservation->addons_total > 0)
                <div class="charge-row"><span>Add-ons</span><span>${{ number_format((float)$reservation->addons_total, 2) }}</span></div>
            @endif
            <div class="charge-row"><span>Sales Tax</span><span>${{ number_format((float)$reservation->tax_amount, 2) }}</span></div>
            <div class="charge-row total"><span>TOTAL</span><span>${{ number_format((float)$reservation->total_price, 2) }}</span></div>
            <div class="charge-row"><span>Payments</span><span style="color:#059669">${{ number_format((float)$reservation->total_paid, 2) }}</span></div>
            @if ($reservation->outstanding_balance > 0)
                <div class="charge-row" style="color:#dc2626; font-weight:bold"><span>Amount Outstanding</span><span>${{ number_format((float)$reservation->outstanding_balance, 2) }}</span></div>
            @else
                <div class="charge-row refund"><span>Status</span><span>✓ PAID IN FULL</span></div>
            @endif
        </td>
    </tr>
</table>

@if ($reservation->payments?->count())
<h3 style="margin-top: 16px; background: #1e293b; color:white; padding: 4px;">Payment History</h3>
<table class="grid">
    <tr>
        <td style="background:#f3f4f6; font-weight:bold; text-align:left; padding:4px">Date</td>
        <td style="background:#f3f4f6; font-weight:bold; text-align:left; padding:4px">Method</td>
        <td style="background:#f3f4f6; font-weight:bold; text-align:left; padding:4px">Reference</td>
        <td style="background:#f3f4f6; font-weight:bold; text-align:right; padding:4px">Amount</td>
    </tr>
    @foreach($reservation->payments as $p)
    <tr>
        <td>{{ optional($p->paid_at)->format('m/d/Y h:i A') }}</td>
        <td style="text-transform:uppercase">{{ str_replace('_', ' ', $p->payment_method) }}
            @if ($p->card_brand) · {{ strtoupper($p->card_brand) }} •••• {{ $p->card_last4 }} @endif
        </td>
        <td style="font-family:monospace">{{ $p->reference ?: '—' }}</td>
        <td style="text-align:right">${{ number_format((float)$p->amount, 2) }}</td>
    </tr>
    @endforeach
</table>
@endif

@if ($activeHold)
<div class="note">
    <strong>Security Deposit Hold:</strong>
    ${{ number_format((float)$activeHold->amount, 2) }} on
    {{ strtoupper($activeHold->card_brand ?? '') }} •••• {{ $activeHold->card_last4 }}
    — status: <strong>{{ strtoupper($activeHold->status) }}</strong>.
    This hold is retained for up to 30 days post-return so we may capture any damage discovered during final inspection. It auto-expires with your card issuer after that.
</div>
@endif

@if ($reservation->return_notes)
<h3>Return Notes</h3>
<p>{{ $reservation->return_notes }}</p>
@endif

<div class="signature-block">
    <table style="width:100%">
    <tr>
        <td style="width:60%">
            @if (!empty($signatureDataUrl))
                <img class="sig-img" src="{{ $signatureDataUrl }}" alt="Signature" />
            @endif
            <div style="border-bottom: 1px solid #111; padding-top: 40px;"></div>
            <div><strong>Renter Signature — {{ $customer->first_name }} {{ $customer->last_name }}</strong></div>
            @if (!empty($signature))
                <div class="sig-meta">Signed {{ optional($signature->signed_at)->format('m/d/Y h:i A') }} · IP: {{ $signature->ip_address }} · SHA256: {{ substr($signature->sha256, 0, 16) }}…</div>
            @endif
        </td>
        <td style="width:40%; vertical-align:top">
            <div><strong>Date:</strong> {{ now()->format('m/d/Y') }}</div>
            <div><strong>RA#:</strong> {{ $reservation->reservation_number }}</div>
            <div style="margin-top:4px; font-size:8px; color:#555">Receipt event: {{ $action }} · Doc type: return_receipt</div>
        </td>
    </tr>
    </table>
</div>

</body>
</html>
