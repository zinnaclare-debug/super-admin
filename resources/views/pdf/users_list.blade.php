<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users List</title>
    <style>
        @page { margin: 18px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        .header {
            margin-bottom: 10px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 4px 0 0;
            font-size: 12px;
            text-transform: uppercase;
        }
        .meta {
            margin-top: 8px;
            font-size: 10px;
            color: #374151;
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
    $generatedLabel = isset($generatedAt) ? \Carbon\Carbon::parse($generatedAt)->format('j M Y, g:i A') : now()->format('j M Y, g:i A');
    $statusFilter = strtoupper((string) ($filters['status'] ?? 'ACTIVE'));
    $roleFilter = strtoupper((string) ($filters['role'] ?? 'ALL'));
    $levelFilter = strtoupper(str_replace('_', ' ', (string) ($filters['level'] ?? 'ALL')));
    $classFilter = (string) ($filters['class'] ?? 'ALL');
    $departmentFilter = (string) ($filters['department'] ?? 'ALL');
@endphp

<div class="header">
    <h1>{{ strtoupper((string) ($school?->name ?? 'School')) }}</h1>
    <p>{{ strtoupper((string) ($school?->location ?? '-')) }}</p>
</div>

<div class="meta">
    Generated: {{ $generatedLabel }} |
    Status: {{ $statusFilter !== '' ? $statusFilter : 'ACTIVE' }} |
    Role: {{ $roleFilter !== '' ? $roleFilter : 'ALL' }} |
    Level: {{ trim($levelFilter) !== '' ? $levelFilter : 'ALL' }} |
    Class: {{ trim($classFilter) !== '' ? $classFilter : 'ALL' }} |
    Department: {{ trim($departmentFilter) !== '' ? strtoupper($departmentFilter) : 'ALL' }}
</div>

<table>
    <thead>
        <tr>
            <th style="width:4%;">S/N</th>
            <th style="width:18%;">Name</th>
            <th style="width:8%;">Role</th>
            <th style="width:11%;">Level</th>
            <th style="width:15%;">Class</th>
            <th style="width:15%;">Department</th>
            <th style="width:14%;">Username</th>
            <th style="width:15%;">Email</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($rows ?? []) as $row)
            @php
                $levels = is_array($row['levels'] ?? null) ? implode(', ', $row['levels']) : (string) ($row['education_level'] ?? '');
                $classes = is_array($row['classes'] ?? null) ? implode(', ', $row['classes']) : (string) ($row['class_name'] ?? '');
                $departments = is_array($row['departments'] ?? null) ? implode(', ', $row['departments']) : (string) ($row['department_name'] ?? '');
            @endphp
            <tr>
                <td class="center">{{ $row['sn'] ?? '-' }}</td>
                <td>{{ $row['name'] ?? '-' }}</td>
                <td>{{ strtoupper((string) ($row['role'] ?? '-')) }}</td>
                <td>{{ $levels !== '' ? strtoupper(str_replace('_', ' ', $levels)) : '-' }}</td>
                <td>{{ $classes !== '' ? $classes : '-' }}</td>
                <td>{{ $departments !== '' ? $departments : '-' }}</td>
                <td>{{ $row['username'] ?? '-' }}</td>
                <td>{{ $row['email'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="center">No users found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
