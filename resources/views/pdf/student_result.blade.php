<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Result</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
        .header-table, .info-table, .result-table, .meta-table, .traits-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { width: 70px; height: 70px; object-fit: contain; }
        .title { text-align: center; }
        .title h2 { margin: 0; font-size: 18px; }
        .title p { margin: 2px 0; }
        .section { margin-top: 12px; }
        .info-table td { padding: 4px 6px; border: 1px solid #d4d4d8; }
        .result-table th, .result-table td,
        .meta-table th, .meta-table td,
        .traits-table th, .traits-table td {
            border: 1px solid #d4d4d8;
            padding: 6px;
        }
        .result-table th, .meta-table th, .traits-table th {
            background: #f4f4f5;
            text-align: left;
        }
        .traits-table td { text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .signature { width: 120px; height: 55px; object-fit: contain; }
        .footer-block { margin-top: 18px; }
        .muted { color: #52525b; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 80px;">
                @if($schoolLogoDataUri)
                    <img class="logo" src="{{ $schoolLogoDataUri }}" alt="School Logo">
                @endif
            </td>
            <td class="title">
                <h2>{{ strtoupper($school?->name ?? 'SCHOOL') }}</h2>
                <p>{{ $session?->academic_year ?: $session?->session_name }} - {{ $term?->name }}</p>
                <p><strong>STUDENT RESULT SHEET</strong></p>
            </td>
            <td style="width: 80px;"></td>
        </tr>
    </table>

    <div class="section">
        <table class="info-table">
            <tr>
                <td><strong>Student Name</strong></td>
                <td>{{ $studentUser?->name }}</td>
                <td><strong>Next Term Begins</strong></td>
                <td>{{ $nextTermBeginDate ? \Carbon\Carbon::parse($nextTermBeginDate)->format('d M, Y') : '-' }}</td>
            </tr>
            <tr>
                <td><strong>Email</strong></td>
                <td>{{ $studentUser?->email ?? '-' }}</td>
                <td><strong>Class</strong></td>
                <td>{{ $class?->name }} ({{ strtoupper($class?->level) }})</td>
            </tr>
            <tr>
                <td><strong>Class Teacher</strong></td>
                <td>{{ $classTeacher?->name ?? '-' }}</td>
                <td><strong>Attendance</strong></td>
                <td>{{ (int)($attendance?->days_present ?? 0) }} day(s) present</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="result-table">
            <thead>
                <tr>
                    <th style="width: 45px;">S/N</th>
                    <th>Subject</th>
                    <th style="width: 70px;">CA</th>
                    <th style="width: 70px;">Exam</th>
                    <th style="width: 70px;">Total</th>
                    <th style="width: 70px;">Grade</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $idx => $row)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row['subject_name'] }}</td>
                        <td class="center">{{ $row['ca'] }}</td>
                        <td class="center">{{ $row['exam'] }}</td>
                        <td class="center">{{ $row['total'] }}</td>
                        <td class="center">{{ $row['grade'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="center">No subject results found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <table class="meta-table">
            <tr>
                <th>Total Score</th>
                <th>Average</th>
                <th>Overall Grade</th>
            </tr>
            <tr>
                <td class="center">{{ $totalScore }}</td>
                <td class="center">{{ number_format((float)$averageScore, 2) }}</td>
                <td class="center">{{ $overallGrade }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="traits-table">
            <thead>
                <tr>
                    <th colspan="{{ count($behaviourTraits) }}">Behaviour Traits (1 - 5)</th>
                </tr>
                <tr>
                    @foreach($behaviourTraits as $trait)
                        <th>{{ $trait['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach($behaviourTraits as $trait)
                        <td class="center">{{ $trait['value'] }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <strong>Class Teacher Comment:</strong>
        <p class="muted">{{ $teacherComment }}</p>
    </div>

    <div class="footer-block">
        <p><strong>Head of School:</strong> {{ $school?->head_of_school_name ?: '-' }}</p>
        @if($headSignatureDataUri)
            <img class="signature" src="{{ $headSignatureDataUri }}" alt="Head Signature">
        @endif
    </div>
</body>
</html>
