<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0.4in; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; line-height: 1.25; }
    .header { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 12px; }
    .brand { font-size: 22px; font-weight: bold; color: #c00; }
    .small { font-size: 9px; color: #444; }
    .section-h { background: #ddd; padding: 4px 8px; font-weight: bold; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 14px; }
    table.fields { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.fields td { border: 1px solid #999; padding: 3px 5px; vertical-align: bottom; }
    table.fields .label { color: #555; font-size: 8px; display: block; margin-bottom: 2px; }
    .auth { color: #c00; font-style: italic; padding: 8px; border: 1px solid #c00; margin-top: 14px; font-size: 9px; }
    .sig-row { margin-top: 18px; }
    .sig-line { border-bottom: 1px solid #111; height: 24px; margin-top: 6px; }
</style>
</head>
<body>

<div class="header">
    <div>
        <div class="brand">AutoGo</div>
        <div class="small">279 route 32<br>Central Valley NY ~ 10917</div>
    </div>
    <div style="text-align:right" class="small">
        (845)-751-1133<br>
        Hershy@autogoco.com
    </div>
</div>

<div class="section-h">Applicant Information</div>
<table class="fields">
    <tr><td colspan="3"><span class="label">Name</span>{{ $fields['applicant_name'] ?? '' }}</td></tr>
    <tr>
        <td><span class="label">Date of birth</span>{{ $fields['applicant_dob'] ?? '' }}</td>
        <td><span class="label">SSN</span>{{ $fields['applicant_ssn'] ?? '' }}</td>
        <td><span class="label">Phone</span>{{ $fields['applicant_phone'] ?? '' }}</td>
    </tr>
    <tr><td colspan="3"><span class="label">Current address</span>{{ $fields['applicant_address'] ?? '' }}</td></tr>
    <tr>
        <td><span class="label">City</span>{{ $fields['applicant_city'] ?? '' }}</td>
        <td><span class="label">State</span>{{ $fields['applicant_state'] ?? '' }}</td>
        <td><span class="label">ZIP Code</span>{{ $fields['applicant_zip'] ?? '' }}</td>
    </tr>
    <tr>
        <td><span class="label">Own / Rent</span>{{ $fields['applicant_own_or_rent'] ?? '' }}</td>
        <td><span class="label">Monthly payment / rent</span>{{ $fields['applicant_monthly_housing'] ?? '' }}</td>
        <td><span class="label">Years</span>{{ $fields['applicant_years_at_addr'] ?? '' }}</td>
    </tr>
</table>

<div class="section-h">Employment Information</div>
<table class="fields">
    <tr><td colspan="3"><span class="label">Current employer</span>{{ $fields['applicant_employer'] ?? '' }}</td></tr>
    <tr>
        <td colspan="2"><span class="label">Employer address</span>{{ $fields['applicant_employer_address'] ?? '' }}</td>
        <td><span class="label">Years employed</span>{{ $fields['applicant_years_employed'] ?? '' }}</td>
    </tr>
    <tr>
        <td><span class="label">City</span>{{ $fields['applicant_employer_city'] ?? '' }}</td>
        <td><span class="label">State</span>{{ $fields['applicant_employer_state'] ?? '' }}</td>
        <td><span class="label">ZIP Code</span>{{ $fields['applicant_employer_zip'] ?? '' }}</td>
    </tr>
    <tr>
        <td><span class="label">Phone</span>{{ $fields['applicant_employer_phone'] ?? '' }}</td>
        <td colspan="2"><span class="label">E-Mail</span>{{ $fields['applicant_employer_email'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="2"><span class="label">Position</span>{{ $fields['applicant_position'] ?? '' }}</td>
        <td><span class="label">Annual income</span>{{ $fields['applicant_annual_income'] ?? '' }}</td>
    </tr>
</table>

<div class="section-h">Co-Applicant Information <span style="font-weight:normal; text-transform:none">(if for a joint account)</span></div>
<table class="fields">
    <tr><td colspan="3"><span class="label">Name</span>{{ $fields['co_name'] ?? '' }}</td></tr>
    <tr>
        <td><span class="label">Date of birth</span>{{ $fields['co_dob'] ?? '' }}</td>
        <td><span class="label">SSN</span>{{ $fields['co_ssn'] ?? '' }}</td>
        <td><span class="label">Phone</span>{{ $fields['co_phone'] ?? '' }}</td>
    </tr>
    <tr><td colspan="3"><span class="label">Current address</span>{{ $fields['co_address'] ?? '' }}</td></tr>
    <tr>
        <td><span class="label">City</span>{{ $fields['co_city'] ?? '' }}</td>
        <td><span class="label">State</span>{{ $fields['co_state'] ?? '' }}</td>
        <td><span class="label">ZIP Code</span>{{ $fields['co_zip'] ?? '' }}</td>
    </tr>
    <tr>
        <td><span class="label">Own / Rent</span>{{ $fields['co_own_or_rent'] ?? '' }}</td>
        <td><span class="label">Monthly housing</span>{{ $fields['co_monthly_housing'] ?? '' }}</td>
        <td><span class="label">Years</span>{{ $fields['co_years_at_addr'] ?? '' }}</td>
    </tr>
    <tr><td colspan="3"><span class="label">Co-applicant employer</span>{{ $fields['co_employer'] ?? '' }}</td></tr>
    <tr>
        <td colspan="2"><span class="label">Position</span>{{ $fields['co_position'] ?? '' }}</td>
        <td><span class="label">Annual income</span>{{ $fields['co_annual_income'] ?? '' }}</td>
    </tr>
</table>

<div class="section-h">Vehicle of Interest</div>
<table class="fields"><tr><td>{{ $fields['vehicle_interest'] ?? '' }}</td></tr></table>

<div class="auth">
    <strong>I Authorize AutoGo and Dealer to submit this application or any other application in
    connection with the proposed transaction to the financial institutions disclose.</strong>
</div>

<table style="width:100%; margin-top:10px">
    <tr>
        <td style="width:70%; vertical-align:bottom">
            @if(!empty($fields['signature_data_url']))
                <img src="{{ $fields['signature_data_url'] }}" style="max-height:40px; display:block;" />
                <div style="border-top:1px solid #111; padding-top:1px"><div class="small">Signature of applicant (electronic)</div></div>
            @else
                <div class="sig-line"></div><div class="small">Signature of applicant</div>
            @endif
        </td>
        <td style="width:30%; padding-left:12px; vertical-align:bottom">
            <div style="border-bottom:1px solid #111; height:40px; padding:6px 0;">{{ !empty($fields['signature_at']) ? \Carbon\Carbon::parse($fields['signature_at'])->format('m/d/Y') : '' }}</div>
            <div class="small">Date</div>
        </td>
    </tr>
</table>

<p style="text-align:center; margin-top:18px; font-size:10px;">
    <strong>PLEASE SIGN AND EMAIL TO <span style="color:#06c">HERSHY@AUTOGOCO.COM</span> WITH A VALID PHOTO ID</strong>
</p>

</body>
</html>
