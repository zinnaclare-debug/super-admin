<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Annual Broadsheet</title>
    <style>
        @page { margin: 14px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 7px;
            color: #111827;
        }
        .meta-line {
            width: 100%;
            display: table;
            margin-bottom: 8px;
        }
        .meta-line .left,
        .meta-line .right {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
        }
        .meta-line .right {
            text-align: right;
        }
        .title {
            text-align: center;
            margin-bottom: 8px;
        }
        .title h1 {
            margin: 0;
            font-size: 15px;
            letter-spacing: 0.3px;
        }
        .title h2 {
            margin: 4px 0 0;
            font-size: 11px;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #111827;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background: #f3f4f6;
            font-weight: 700;
        }
        .left-text {
            text-align: left;
        }
        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>
@php
    $sessionLabel = strtoupper((string) ($session?->academic_year ?: $session?->session_name ?: '-'));
    $levelLabel = strtoupper((string) ($level ?? '-'));
    $departmentLabel = strtoupper((string) ($department ?: 'ALL'));
    $nowLabel = now()->format('n/j/Y g:i A');
@endphp

<div class="meta-line">
    <div class="left">{{ $nowLabel }}</div>
    <div class="right">ANNUAL BROADSHEET</div>
</div>

<div class="title">
    <h1>{{ strtoupper((string) ($schoolName ?: 'SCHOOL NAME')) }}</h1>
    <h2>ANNUAL BROADSHEET FOR {{ $levelLabel }} - {{ $sessionLabel }} SESSION</h2>
    <h2>DEPARTMENT: {{ $departmentLabel }}</h2>
</div>

<table>
    <thead>
    <tr>
        <th class="left-text nowrap">STUDENT NAME</th>
        <th class="left-text nowrap">CLASS</th>
        @foreach($subjects as $subject)
            <th class="nowrap">{{ strtoupper((string) ($subject['short_code'] ?? $subject['code'] ?? $subject['name'] ?? '-')) }}</th>
        @endforeach
        <th class="nowrap">TOTAL</th>
        <th class="nowrap">AVERAGE</th>
        <th class="nowrap">POSITION</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            <td class="left-text">{{ strtoupper((string) ($row['name'] ?? '-')) }}</td>
            <td class="left-text">{{ strtoupper((string) ($row['class_name'] ?? '-')) }}</td>
            @foreach($subjects as $subject)
                @php
                    $subjectKey = (string) ($subject['id'] ?? '');
                    $value = $row['scores'][$subjectKey] ?? null;
                @endphp
                <td>{{ $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') }}</td>
            @endforeach
            <td>{{ rtrim(rtrim(number_format((float) ($row['total'] ?? 0), 2, '.', ''), '0'), '.') }}</td>
            <td>{{ rtrim(rtrim(number_format((float) ($row['average'] ?? 0), 2, '.', ''), '0'), '.') }}</td>
            <td>{{ $row['position_label'] ?? '-' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($subjects) + 5 }}">No broadsheet result data found.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
