<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Login Details</title>
    <style>
        @page { margin: 18px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        .header {
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 4px 0 0;
            font-size: 12px;
        }
        .meta {
            margin-top: 6px;
            font-size: 10px;
            color: #374151;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            border: 1px solid #111827;
            padding: 5px 6px;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            text-align: left;
            font-weight: bold;
        }
        .center { text-align: center; }
    </style>
</head>
<body>
@php
    $roleFilter = trim((string) ($filters['role'] ?? ''));
    $levelFilter = trim((string) ($filters['level'] ?? ''));
    $classFilter = (int) ($filters['class_id'] ?? 0);
    $departmentFilter = trim((string) ($filters['department'] ?? ''));
    $generatedLabel = isset($generatedAt) ? \Carbon\Carbon::parse($generatedAt)->format('j M Y, g:i A') : now()->format('j M Y, g:i A');
@endphp

<div class="header">
    <h1>{{ strtoupper((string) ($school?->name ?? 'School')) }}</h1>
    <p>{{ strtoupper((string) ($school?->location ?? '-')) }}</p>
    <div class="meta">
        Generated: {{ $generatedLabel }} |
        Role: {{ $roleFilter !== '' ? strtoupper($roleFilter) : 'ALL' }} |
        Level: {{ $levelFilter !== '' ? strtoupper(str_replace('_', ' ', $levelFilter)) : 'ALL' }} |
        Class ID: {{ $classFilter > 0 ? $classFilter : 'ALL' }} |
        Department: {{ $departmentFilter !== '' ? strtoupper($departmentFilter) : 'ALL' }}
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:4%;">S/N</th>
            <th style="width:16%;">Name</th>
            <th style="width:8%;">Role</th>
            <th style="width:10%;">Education Level</th>
            <th style="width:12%;">Class</th>
            <th style="width:10%;">Department</th>
            <th style="width:11%;">Username</th>
            <th style="width:15%;">Email</th>
            <th style="width:8%;">Password</th>
            <th style="width:10%;">Last Password Set</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($rows ?? []) as $row)
            <tr>
                <td class="center">{{ $row['sn'] ?? '-' }}</td>
                <td>{{ $row['name'] ?? '-' }}</td>
                <td>{{ strtoupper((string) ($row['role'] ?? '-')) }}</td>
                <td>{{ strtoupper(str_replace('_', ' ', (string) ($row['level'] ?? '-'))) }}</td>
                <td>{{ $row['class_name'] ?? '-' }}</td>
                <td>{{ $row['department'] ?? '-' }}</td>
                <td>{{ $row['username'] ?? '-' }}</td>
                <td>{{ $row['email'] ?? '-' }}</td>
                <td>{{ $row['password'] ?? '-' }}</td>
                <td>{{ $row['last_password_set_at'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="center">No login details found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
