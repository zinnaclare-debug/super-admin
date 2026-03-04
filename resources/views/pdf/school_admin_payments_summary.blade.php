<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Payments Summary</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
        .header { text-align: center; margin-bottom: 8px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 4px 0 0; font-size: 12px; text-transform: uppercase; }
        .sub { margin-top: 8px; font-size: 10px; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #111827; padding: 5px 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-weight: bold; }
        .money { text-align: right; }
        .center { text-align: center; }
        .tfoot td { font-weight: bold; background: #eef2ff; }
    </style>
</head>
<body>
@php
    $modeLabel = $viewMode === 'outstanding' ? 'OUTSTANDING PAYMENTS' : 'PAYMENT RECORDS';
    $statusLabel = strtoupper((string) ($statusMode ?? 'ALL'));
    $generatedLabel = isset($generatedAt) ? \Carbon\Carbon::parse($generatedAt)->format('j M Y, g:i A') : now()->format('j M Y, g:i A');
@endphp

<div class="header">
    <h1>{{ strtoupper((string) ($school?->name ?? 'SCHOOL')) }}</h1>
    <p>{{ strtoupper((string) ($school?->location ?? '-')) }}</p>
</div>

<div class="sub">
    <strong>{{ $modeLabel }}</strong> |
    Session: {{ $session?->session_name ?: ($session?->academic_year ?: '-') }} |
    Term: {{ $term?->name ?: '-' }} |
    Status: {{ $statusLabel }} |
    Level: {{ strtoupper((string) ($filters['level'] ?? 'ALL')) ?: 'ALL' }} |
    Class: {{ (string) ($filters['class'] ?? 'ALL') ?: 'ALL' }} |
    Department: {{ (string) ($filters['department'] ?? 'ALL') ?: 'ALL' }} |
    Generated: {{ $generatedLabel }}
</div>

<table>
    <thead>
    <tr>
        <th style="width:4%;">S/N</th>
        <th style="width:24%;">Student Name</th>
        <th style="width:12%;">Level</th>
        <th style="width:14%;">Class</th>
        <th style="width:14%;">Department</th>
        <th style="width:16%;">Amount Paid (NGN)</th>
        <th style="width:16%;">Outstanding (NGN)</th>
    </tr>
    </thead>
    <tbody>
    @forelse(($rows ?? []) as $row)
        <tr>
            <td class="center">{{ $row['sn'] ?? '-' }}</td>
            <td>{{ $row['student']['name'] ?? '-' }}</td>
            <td>{{ strtoupper(str_replace('_', ' ', (string) ($row['level'] ?? '-'))) }}</td>
            <td>{{ $row['class_name'] ?? '-' }}</td>
            <td>{{ $row['department_name'] ?? '-' }}</td>
            <td class="money">{{ number_format((float) ($row['amount_paid'] ?? 0), 2) }}</td>
            <td class="money">{{ number_format((float) ($row['amount_outstanding'] ?? 0), 2) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="center">No records found.</td>
        </tr>
    @endforelse
    </tbody>
    <tfoot class="tfoot">
        <tr>
            <td colspan="5" class="money">TOTAL</td>
            <td class="money">{{ number_format((float) ($totals['paid'] ?? 0), 2) }}</td>
            <td class="money">{{ number_format((float) ($totals['outstanding'] ?? 0), 2) }}</td>
        </tr>
    </tfoot>
</table>
</body>
</html>

