<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px; }
        * { box-sizing: border-box; }
        body { color: #16243a; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        .top-line, .bottom-line { height: 7px; background: {{ $primaryColor }}; }
        .header { padding: 20px 4px 15px; }
        .header-table, .party-table, .meta-table, .amount-table, .balance-table { border-collapse: collapse; width: 100%; }
        .title { color: {{ $primaryColor }}; font-size: 25px; font-weight: 700; letter-spacing: .4px; margin: 0; }
        .sub-title { color: #6b7b91; font-size: 10px; margin-top: 5px; }
        .status { border-radius: 12px; color: #fff; display: inline-block; font-size: 10px; font-weight: 700; padding: 7px 13px; }
        .status-paid { background: #15803d; }
        .status-part { background: #b45309; }
        .panel { background: #edf6fb; border: 1px solid #d4e7f2; margin-top: 8px; padding: 16px; }
        .party-title { color: {{ $primaryColor }}; font-size: 10px; font-weight: 700; letter-spacing: .5px; margin-bottom: 6px; text-transform: uppercase; }
        .party-name { color: #12233c; font-size: 12px; font-weight: 700; }
        .muted { color: #64748b; }
        .meta-table { margin: 18px 0 12px; }
        .meta-table td { border-bottom: 1px solid #dbe5ef; padding: 8px 7px; width: 25%; }
        .meta-label { color: #64748b; display: block; font-size: 8px; text-transform: uppercase; }
        .meta-value { color: #12233c; display: block; font-size: 10px; font-weight: 700; margin-top: 2px; }
        .amount-table { margin-top: 11px; }
        .amount-table th { background: {{ $primaryColor }}; color: #fff; font-size: 10px; padding: 9px 11px; text-align: left; }
        .amount-table th:last-child, .amount-table td:last-child { text-align: right; }
        .amount-table td { border-bottom: 1px solid #dbe5ef; padding: 10px 11px; }
        .amount-total td { background: #e1f0fa; color: {{ $primaryColor }}; font-size: 12px; font-weight: 700; }
        .balance-table { margin-top: 16px; }
        .balance-table td { border: 1px solid #d5e3ee; padding: 9px 11px; }
        .balance-table .label { color: #64748b; font-size: 9px; }
        .balance-table .value { color: #12233c; font-size: 11px; font-weight: 700; text-align: right; }
        .thank-you { color: {{ $primaryColor }}; font-size: 13px; font-weight: 700; margin: 25px 0 6px; text-align: center; }
        .footer { border-top: 1px solid #dbe5ef; color: #64748b; font-size: 8px; padding-top: 9px; text-align: center; }
        .logo { max-height: 44px; max-width: 125px; }
    </style>
</head>
<body>
    @php
        $isComplete = (float) ($summary['remaining_total'] ?? 0) <= 0.01;
        $currency = $receipt->currency ?: 'NGN';
        $money = fn ($value) => $currency . ' ' . number_format((float) $value, 2);
    @endphp
    <div class="top-line"></div>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 72%; vertical-align: top;">
                    <h1 class="title">PAYMENT RECEIPT</h1>
                    <div class="sub-title">Official school subscription payment record</div>
                </td>
                <td style="width: 28%; text-align: right; vertical-align: top;">
                    <span class="status {{ $isComplete ? 'status-paid' : 'status-part' }}">
                        STATUS: {{ $isComplete ? 'PAID' : 'PART PAYMENT' }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="panel">
        <table class="party-table">
            <tr>
                <td style="width: 53%; vertical-align: top;">
                    <div class="party-title">Received By</div>
                    <div class="party-name">{{ $issuerName }}</div>
                    <div class="muted">{{ $issuerEmail }}</div>
                </td>
                <td style="width: 47%; vertical-align: top; text-align: right;">
                    @if($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="School logo">@endif
                    <div class="party-title">Received From</div>
                    <div class="party-name">{{ $school->name }}</div>
                    @if($school->contact_email || $school->email)<div class="muted">{{ $school->contact_email ?: $school->email }}</div>@endif
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td><span class="meta-label">Receipt Number</span><span class="meta-value">{{ $receipt->receipt_number }}</span></td>
            <td><span class="meta-label">Invoice Reference</span><span class="meta-value">{{ $invoice->reference }}</span></td>
            <td><span class="meta-label">Academic Session</span><span class="meta-value">{{ $sessionName ?: '-' }}</span></td>
            <td><span class="meta-label">Term</span><span class="meta-value">{{ $termName ?: '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="meta-label">Receipt Date</span><span class="meta-value">{{ optional($receipt->created_at)->format('d M Y, h:i A') }}</span></td>
            <td><span class="meta-label">Billing Cycle</span><span class="meta-value">{{ ucfirst($invoice->billing_cycle) }}</span></td>
            <td><span class="meta-label">Students Billed</span><span class="meta-value">{{ number_format((int) $invoice->student_count_snapshot) }}</span></td>
            <td><span class="meta-label">Payment Type</span><span class="meta-value">Subscription Payment</span></td>
        </tr>
    </table>

    <table class="amount-table">
        <thead><tr><th>Description</th><th>Amount</th></tr></thead>
        <tbody>
            <tr><td>Subscription subtotal paid</td><td>{{ $money($receipt->subtotal_paid) }}</td></tr>
            <tr><td>Processing fee paid</td><td>{{ $money($receipt->processing_fee_paid) }}</td></tr>
            <tr class="amount-total"><td>Total amount paid</td><td>{{ $money($receipt->total_paid) }}</td></tr>
        </tbody>
    </table>

    <table class="balance-table">
        <tr><td class="label">Invoice Total</td><td class="value">{{ $money($summary['invoice_total'] ?? 0) }}</td></tr>
        <tr><td class="label">Total Recorded Paid</td><td class="value">{{ $money($summary['paid_total'] ?? 0) }}</td></tr>
        <tr><td class="label">Amount Remaining</td><td class="value">{{ $money($summary['remaining_total'] ?? 0) }}</td></tr>
    </table>

    <div class="thank-you">Thank you for your payment.</div>
    <div class="footer">This is a computer-generated receipt from {{ $issuerName }}. Please retain it for your records.</div>
    <div class="bottom-line" style="margin-top: 18px;"></div>
</body>
</html>