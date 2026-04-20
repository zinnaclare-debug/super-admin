<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Subscription Invoice</title>
    <style>
        @page { margin: 20px; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
            background: #ffffff;
        }
        .sheet {
            border: 1px solid #dbe3ef;
            background: #ffffff;
        }
        .hero {
            background: {{ $primaryColor ?? '#0f172a' }};
            color: #ffffff;
            padding: 20px 24px 14px;
        }
        .hero-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hero-table td {
            vertical-align: top;
        }
        .hero-title {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .hero-subtitle {
            margin-top: 4px;
            font-size: 11px;
            opacity: 0.9;
        }
        .hero-meta {
            text-align: right;
            font-size: 10px;
            line-height: 1.6;
        }
        .brand-strip {
            background: {{ $accentColor ?? '#0f766e' }};
            color: #ffffff;
            padding: 14px 24px;
        }
        .brand-table {
            width: 100%;
            border-collapse: collapse;
        }
        .brand-table td {
            vertical-align: middle;
        }
        .logo-wrap {
            width: 76px;
        }
        .logo {
            width: 62px;
            height: 62px;
            object-fit: contain;
            border-radius: 10px;
            background: #ffffff;
            padding: 4px;
        }
        .school-name {
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .school-details {
            margin-top: 3px;
            font-size: 10px;
            line-height: 1.5;
        }
        .summary-panel {
            margin: 18px 24px 0;
            background: {{ $primaryTint ?? '#e2e8f0' }};
            border: 1px solid {{ $accentTint ?? '#d1fae5' }};
            padding: 14px 16px;
        }
        .summary-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-grid td {
            width: 50%;
            padding: 4px 0;
            vertical-align: top;
        }
        .muted {
            display: block;
            color: #475569;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .strong {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .section {
            margin: 18px 24px 0;
        }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: {{ $primaryColor ?? '#0f172a' }};
            margin-bottom: 8px;
        }
        .details-table,
        .totals-table,
        .bank-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table td,
        .totals-table td,
        .bank-table td {
            border: 1px solid #d9e2ec;
            padding: 8px 10px;
        }
        .details-table td:first-child,
        .bank-table td:first-child,
        .totals-table td:first-child {
            width: 38%;
            background: #f8fafc;
            font-weight: 700;
        }
        .totals-table .total-row td {
            background: {{ $accentColor ?? '#0f766e' }};
            color: #ffffff;
            font-weight: 700;
            font-size: 12px;
        }
        .pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: {{ $accentTint ?? '#d1fae5' }};
            color: {{ $primaryColor ?? '#0f172a' }};
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .two-col {
            width: 100%;
            border-collapse: separate;
            border-spacing: 18px 0;
            margin: 0 6px 0;
        }
        .two-col td {
            width: 50%;
            vertical-align: top;
        }
        .note-box {
            margin: 18px 24px 0;
            background: #f8fafc;
            border: 1px dashed {{ $accentColor ?? '#0f766e' }};
            padding: 12px 14px;
        }
        .note-box p {
            margin: 0;
            line-height: 1.6;
            color: #334155;
        }
        .footer {
            margin: 16px 24px 20px;
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }
        .money {
            text-align: right;
            font-weight: 700;
        }
    </style>
</head>
<body>
@php
    $generatedLabel = isset($generatedAt) ? \Carbon\Carbon::parse($generatedAt)->format('j M Y, g:i A') : now()->format('j M Y, g:i A');
    $currencyCode = (string) ($currency ?? 'NGN');
    $money = static fn ($value) => $currencyCode . ' ' . number_format((float) $value, 2);
@endphp

<div class="sheet">
    <div class="hero">
        <table class="hero-table">
            <tr>
                <td>
                    <div class="hero-title">Invoice</div>
                    <div class="hero-subtitle">{{ $billingLabel ?? 'School Subscription Invoice' }}</div>
                </td>
                <td class="hero-meta">
                    <div><strong>Invoice No:</strong> {{ $invoiceNumber ?? '-' }}</div>
                    <div><strong>Status:</strong> {{ strtoupper((string) ($statusLabel ?? 'Pending Payment')) }}</div>
                    <div><strong>Generated:</strong> {{ $generatedLabel }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="brand-strip">
        <table class="brand-table">
            <tr>
                <td class="logo-wrap">
                    @if(!empty($logoDataUri))
                        <img class="logo" src="{{ $logoDataUri }}" alt="School Logo" />
                    @endif
                </td>
                <td>
                    <div class="school-name">{{ strtoupper((string) ($school?->name ?? 'School')) }}</div>
                    <div class="school-details">
                        @if(!empty($schoolLocation))
                            <div>{{ $schoolLocation }}</div>
                        @endif
                        @if(!empty($schoolEmail))
                            <div>{{ $schoolEmail }}</div>
                        @endif
                        @if(!empty($schoolPhone))
                            <div>{{ $schoolPhone }}</div>
                        @endif
                    </div>
                </td>
                <td style="text-align:right;">
                    <span class="pill">{{ ($quote['billing_cycle'] ?? 'termly') === 'yearly' ? 'Yearly Cover' : 'Termly Cover' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary-panel">
        <table class="summary-grid">
            <tr>
                <td>
                    <span class="muted">Current Session</span>
                    <span class="strong">{{ $currentSessionName ?? '-' }}</span>
                </td>
                <td>
                    <span class="muted">Current Term</span>
                    <span class="strong">{{ $currentTermName ?? '-' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="muted">Amount Per Student</span>
                    <span class="strong">{{ $money(($quote['amount_per_student_per_term'] ?? 0)) }}</span>
                </td>
                <td>
                    <span class="muted">Total Number of Students</span>
                    <span class="strong">{{ number_format((int) ($studentCount ?? 0)) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="two-col">
        <tr>
            <td>
                <div class="section">
                    <div class="section-title">Billing Summary</div>
                    <table class="details-table">
                        <tr>
                            <td>Billing Option</td>
                            <td>{{ ($quote['billing_cycle'] ?? 'termly') === 'yearly' ? 'Yearly' : 'Termly' }}</td>
                        </tr>
                        <tr>
                            <td>Terms Covered</td>
                            <td>{{ (int) ($quote['terms_covered'] ?? 1) }}</td>
                        </tr>
                        <tr>
                            <td>Amount Per Student</td>
                            <td>{{ $money(($quote['amount_per_student_per_term'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td>Total Number of Students</td>
                            <td>{{ number_format((int) ($studentCount ?? 0)) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <div class="section-title">Payment Totals</div>
                    <table class="totals-table">
                        <tr>
                            <td>Total Bill</td>
                            <td class="money">{{ $money(($quote['subtotal'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td>Processing Fee ({{ number_format((float) ($quote['tax_percent'] ?? 0), 2) }}%)</td>
                            <td class="money">{{ $money(($quote['tax_amount'] ?? 0)) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td>Total Amount</td>
                            <td class="money">{{ $money(($quote['total_amount'] ?? 0)) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Bank Account Information</div>
        <table class="bank-table">
            <tr>
                <td>Bank Name</td>
                <td>{{ $bankName ?? '-' }}</td>
            </tr>
            <tr>
                <td>Bank Account Number</td>
                <td>{{ $bankAccountNumber ?? '-' }}</td>
            </tr>
            <tr>
                <td>Bank Account Name</td>
                <td>{{ $bankAccountName ?? '-' }}</td>
            </tr>
            @if(!empty($existingInvoice?->reference))
                <tr>
                    <td>Payment Reference</td>
                    <td>{{ $existingInvoice->reference }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="note-box">
        <p>
            Please use the invoice number <strong>{{ $invoiceNumber ?? '-' }}</strong> as your payment reference.
            @if(!empty($bankNote))
                {{ ' ' . $bankNote }}
            @else
                Payment will be confirmed after successful review or verification.
            @endif
        </p>
    </div>

    <div class="footer">
        This is a system-generated school subscription invoice prepared from the current super-admin billing configuration and the school's active branding setup.
    </div>
</div>
</body>
</html>
