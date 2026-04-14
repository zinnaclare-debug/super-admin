<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Fee Invoice</title>
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
        .invoice-meta {
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
        .summary-signature-layout {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .summary-signature-layout td {
            vertical-align: top;
        }
        .summary-signature-layout .signature-cell {
            width: 56%;
            padding-right: 18px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
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
        .signature-card {
            min-height: 106px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 12px;
        }
        .signature-label {
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .signature-image-wrap {
            margin-top: 8px;
            min-height: 58px;
        }
        .signature-image {
            max-width: 180px;
            max-height: 64px;
            object-fit: contain;
        }
        .signature-line {
            margin-top: 8px;
            border-top: 1px solid #475569;
            padding-top: 4px;
            font-size: 10px;
            color: #334155;
        }
        .status-box {
            margin-top: 12px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 10px;
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
            <td class="invoice-meta" style="width:180px;">
                <div><strong>Invoice No:</strong> {{ $invoiceNumber ?? '-' }}</div>
                <div><strong>Status:</strong> {{ strtoupper((string) ($statusLabel ?? '-')) }}</div>
                <div><strong>Generated:</strong> {{ $generatedLabel }}</div>
            </td>
        </tr>
    </table>
    <div class="title-band">SCHOOL FEE INVOICE</div>
</div>

<table class="info-grid">
    <tr>
        <td>
            <div class="label">Student Name</div>
            <div class="value">{{ $studentUser?->name ?? '-' }}</div>
        </td>
        <td>
            <div class="label">Username</div>
            <div class="value">{{ $studentUser?->username ?? '-' }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">Email</div>
            <div class="value">{{ $studentUser?->email ?? '-' }}</div>
        </td>
        <td>
            <div class="label">Education Level</div>
            <div class="value">{{ strtoupper(str_replace('_', ' ', (string) ($studentLevel ?? '-'))) }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">Class / Department</div>
            <div class="value">
                {{ $className ?: '-' }}
                @if(!empty($departmentName))
                    / {{ $departmentName }}
                @endif
            </div>
        </td>
        <td>
            <div class="label">Session / Term</div>
            <div class="value">
                {{ $session?->session_name ?: ($session?->academic_year ?: '-') }} /
                {{ $term?->name ?: '-' }}
            </div>
        </td>
    </tr>
</table>

<div class="status-box">
    <div class="label">Invoice Status</div>
    <div class="value">{{ $statusMessage ?? '-' }}</div>
</div>

<table class="items-table">
    <thead>
        <tr>
            <th style="width:8%;">No.</th>
            <th>Fee Description</th>
            <th style="width:24%;">Amount (NGN)</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($lineItems ?? []) as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $row['description'] ?? '-' }}</td>
                <td class="money">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td>1</td>
                <td>School Fees</td>
                <td class="money">{{ number_format((float) ($amountDue ?? 0), 2) }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="summary-signature-layout">
    <tr>
        <td class="signature-cell">
            <div class="signature-card">
                <div class="signature-label">Authorized Signature</div>
                @if(!empty($headSignatureDataUri))
                    <div class="signature-image-wrap">
                        <img class="signature-image" src="{{ $headSignatureDataUri }}" alt="Head Signature" />
                    </div>
                @endif
                <div class="signature-line">{{ $school?->head_of_school_name ?: 'Head of School' }}</div>
            </div>
        </td>
        <td>
            <table class="summary-table">
                <tr>
                    <td>Total Invoice</td>
                    <td class="money">{{ number_format((float) ($amountDue ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Total Paid So Far</td>
                    <td class="money">{{ number_format((float) ($totalPaid ?? 0), 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Outstanding Balance</td>
                    <td class="money">{{ number_format((float) ($outstanding ?? 0), 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="footnote">
    This is a system-generated invoice based on the current school fee configuration for this term.
</div>
</body>
</html>

