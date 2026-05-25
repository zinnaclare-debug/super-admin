<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; }
        .header { background: #0f3d59; color: #fff; padding: 16px; border-radius: 10px; }
        .title { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
        .meta { margin-top: 8px; }
        .pill { display: inline-block; background: #fcd34d; color: #082f49; padding: 5px 9px; border-radius: 999px; font-weight: 700; margin-right: 6px; }
        h3 { margin: 18px 0 8px; color: #0f3d59; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #e0f2fe; color: #0f172a; text-align: left; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .small { color: #475569; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $schoolName }} Staff Attendance Report</p>
        <div class="meta">
            <span class="pill">{{ $sessionLabel }}</span>
            <span class="pill">{{ $termName }}</span>
            <span class="pill">{{ $expectedDays }} recorded day{{ (int) $expectedDays === 1 ? '' : 's' }}</span>
            <span class="pill">Generated {{ optional($generatedAt)->format('M d, Y h:i A') }}</span>
        </div>
    </div>

    <h3>Term Summary</h3>
    <table>
        <thead>
            <tr>
                <th>S/N</th>
                <th>Staff Name</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Late</th>
                <th>Far From School Present</th>
                <th>Expected Days</th>
                <th>Attendance %</th>
                <th>Last Sign In</th>
                <th>Last Sign Out</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summary as $row)
                <tr>
                    <td>{{ $row['sn'] }}</td>
                    <td>{{ $row['staff_name'] }}</td>
                    <td>{{ $row['present'] }}</td>
                    <td>{{ $row['absent'] }}</td>
                    <td>{{ $row['late'] }}</td>
                    <td>{{ $row['far_from_school_present'] }}</td>
                    <td>{{ $row['expected_days'] }}</td>
                    <td>{{ $row['attendance_percent'] === null ? '-' : $row['attendance_percent'] . '%' }}</td>
                    <td>{{ $row['last_sign_in'] ? optional($row['last_sign_in'])->format('M d, Y h:i A') : '-' }}</td>
                    <td>{{ $row['last_sign_out'] ? optional($row['last_sign_out'])->format('M d, Y h:i A') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No staff found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Daily Sign In / Sign Out Records</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Staff</th>
                <th>Status</th>
                <th>Sign In</th>
                <th>Sign Out</th>
                <th>Sign In Location</th>
                <th>Sign Out Location</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>{{ optional($record->attendance_date)->format('M d, Y') }}</td>
                    <td>
                        <strong>{{ $record->staffUser?->name ?: 'Staff' }}</strong><br>
                        <span class="small">{{ $record->staffUser?->email ?: $record->staffUser?->username }}</span>
                    </td>
                    <td>{{ str_replace('_', ' ', $record->status ?: 'present') }}</td>
                    <td>{{ $record->signed_in_at ? optional($record->signed_in_at)->format('h:i A') : '-' }}</td>
                    <td>{{ $record->signed_out_at ? optional($record->signed_out_at)->format('h:i A') : '-' }}</td>
                    <td>
                        {{ str_replace('_', ' ', $record->location_status ?: 'unknown') }}<br>
                        <span class="small">{{ $record->distance_from_school_meters ?? '-' }}m from school</span>
                    </td>
                    <td>
                        {{ str_replace('_', ' ', $record->sign_out_location_status ?: 'not signed out') }}<br>
                        <span class="small">{{ $record->sign_out_distance_from_school_meters ?? '-' }}m from school</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No attendance records found for this term.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
