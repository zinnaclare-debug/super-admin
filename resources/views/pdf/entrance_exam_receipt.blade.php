<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Entrance Exam Receipt</title>
    <style>
        @page { margin: 20px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
        }
        .header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { width: 72px; height: 72px; object-fit: contain; }
        .school-title { text-align: center; }
        .school-title h1 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #0f766e;
        }
        .school-title p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #334155;
            text-transform: uppercase;
        }
        .receipt-meta {
            text-align: right;
            font-size: 10px;
            color: #334155;
            line-height: 1.4;
        }
        .title-band {
            margin-top: 8px;
            text-align: center;
            background: #0f766e;
            color: #fff;
            padding: 7px 10px;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }
        .info-grid {
            margin-top: 12px;
            width: 100%;
            border-collapse: collapse;
        }
        .info-grid td {
            width: 50%;
            border: 1px solid #cbd5e1;
            padding: 8px;
            vertical-align: top;
        }
        .label { color: #475569; font-size: 10px; }
        .value { font-size: 11px; font-weight: 600; margin-top: 2px; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #0f172a;
            padding: 6px;
        }
        .items-table th {
            background: #0f766e;
            color: #fff;
            text-transform: uppercase;
            font-size: 10px;
        }
        .money { text-align: right; }
        .summary-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .summary-table td {
            border: 1px solid #0f172a;
            padding: 6px;
        }
        .summary-table td:first-child {
            background: #f1f5f9;
            font-weight: bold;
        }
        .summary-table .total-row td {
            background: #0f766e;
            color: #fff;
            font-weight: bold;
        }
        .footnote {
            margin-top: 16px;
            font-size: 10px;
            color: #475569;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $paymentAt = $application?->paid_at ?: $application?->created_at;
    $paymentAtLabel = $paymentAt ? \Carbon\Carbon::parse($paymentAt)->format('j M Y, g:i A') : '-';
    $generatedLabel = isset($generatedAt) ? \Carbon\Carbon::parse($generatedAt)->format('j M Y, g:i A') : now()->format('j M Y, g:i A');
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:85px;">
                @if(!empty($logoDataUri))
                    <img class="logo" src="{{ $logoDataUri }}" alt="School Logo" />
                @endif
            </td>
            <td class="school-title">
                <h1>{{ strtoupper((string) ($school?->name ?? 'SCHOOL')) }}</h1>
                <p>{{ strtoupper((string) ($school?->location ?? '-')) }}</p>
            </td>
            <td class="receipt-meta" style="width:180px;">
                <div><strong>Reference:</strong> {{ $application?->payment_reference ?? '-' }}</div>
                <div><strong>Status:</strong> {{ strtoupper((string) ($application?->payment_status ?? '-')) }}</div>
                <div><strong>Generated:</strong> {{ $generatedLabel }}</div>
            </td>
        </tr>
    </table>
    <div class="title-band">ENTRANCE EXAM RECEIPT</div>
</div>

<table class="info-grid">
    <tr>
        <td>
            <div class="label">Applicant Name</div>
            <div class="value">{{ $application?->full_name ?? '-' }}</div>
        </td>
        <td>
            <div class="label">Application Number</div>
            <div class="value">{{ $application?->application_number ?? '-' }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">Email</div>
            <div class="value">{{ $application?->email ?? '-' }}</div>
        </td>
        <td>
            <div class="label">Phone</div>
            <div class="value">{{ $application?->phone ?? '-' }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">Class Applied For</div>
            <div class="value">{{ $application?->applying_for_class ?? '-' }}</div>
        </td>
        <td>
            <div class="label">Payment Date & Time</div>
            <div class="value">{{ $paymentAtLabel }}</div>
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th style="width:8%">No.</th>
            <th>Fee Description</th>
            <th style="width:24%">Amount (NGN)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1</td>
            <td>Entrance Exam Application Fee</td>
            <td class="money">{{ number_format((float) ($application?->amount_due ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>2</td>
            <td>Tax ({{ number_format((float) ($application?->tax_rate ?? 0), 2) }}%)</td>
            <td class="money">{{ number_format((float) ($application?->tax_amount ?? 0), 2) }}</td>
        </tr>
    </tbody>
</table>

<table class="summary-table">
    <tr>
        <td>Total Fee</td>
        <td class="money">{{ number_format((float) ($application?->amount_total ?? 0), 2) }}</td>
    </tr>
    <tr class="total-row">
        <td>Amount Paid</td>
        <td class="money">{{ number_format((float) ($application?->amount_paid ?? 0), 2) }}</td>
    </tr>
</table>

<div class="footnote">
    This is a system-generated receipt.
</div>
</body>
</html>
