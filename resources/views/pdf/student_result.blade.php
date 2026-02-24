@php($embedded = (bool) ($embedded ?? false))
@if(!$embedded)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Result</title>
@endif
    <style>
        @page { margin: 18px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 7px;
            color: #111827;
        }
        .sheet {
            position: relative;
            border: 1px solid #d1d5db;
            padding: 10px;
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            top: 28%;
            left: 50%;
            width: 320px;
            height: 320px;
            margin-left: -160px;
            opacity: 0.07;
            z-index: 0;
            object-fit: contain;
        }
        .content {
            position: relative;
            z-index: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td {
            border: 1px solid #111;
            padding: 6px;
            vertical-align: middle;
        }
        .title-wrap {
            text-align: center;
        }
        .title-wrap h1 {
            margin: 0;
            font-size: 26px;
            letter-spacing: 0.6px;
        }
        .title-wrap p {
            margin: 2px 0 0;
            font-size: 12px;
        }
        .id-photo, .school-logo {
            width: 78px;
            height: 78px;
            border: 1px solid #111;
            object-fit: cover;
        }
        .section-title {
            margin-top: 8px;
            border: 1px solid #111;
            border-bottom: 0;
            text-align: center;
            font-weight: bold;
            padding: 6px 8px;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        .meta td, .meta th,
        .scores td, .scores th,
        .psycho td, .psycho th,
        .comment td, .comment th,
        .key-rate td, .key-rate th {
            border: 1px solid #111;
            padding: 4px 6px;
        }
        .meta th, .scores th, .psycho th, .comment th, .key-rate th {
            background: #f3f4f6;
            text-align: left;
        }
        .center { text-align: center; }
        .small { font-size: 10px; }
        .grades-key {
            margin-top: 4px;
            border: 1px solid #111;
            padding: 4px 6px;
            font-size: 10px;
        }
        .psycho-grid {
            margin-top: 8px;
            width: 100%;
        }
        .psycho-grid td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        .signature-box {
            margin-top: 6px;
            border: 1px solid #111;
            padding: 6px;
            min-height: 60px;
        }
        .signature {
            width: 130px;
            height: 48px;
            object-fit: contain;
            border-bottom: 1px dashed #6b7280;
        }
        .signature-placeholder {
            width: 130px;
            height: 48px;
            border-bottom: 1px dashed #6b7280;
        }
    </style>
@if(!$embedded)
</head>
<body>
@endif
@php
    $timesPresent = (int) ($attendance?->days_present ?? 0);
    $timesOpened = (int) ($attendanceSetting?->total_school_days ?? 0);
    $totalObtainable = max(1, count($rows)) * 100;
    $nextTermBeginLabel = '-';
    if (!empty($nextTermBeginDate)) {
        try {
            $nextTermBeginLabel = \Carbon\Carbon::parse($nextTermBeginDate)->format('jS M, Y');
        } catch (\Throwable $e) {
            $nextTermBeginLabel = '-';
        }
    }
@endphp
<div class="sheet">
    @if($schoolLogoDataUri)
        <img class="watermark" src="{{ $schoolLogoDataUri }}" alt="">
    @endif

    <div class="content">
        <table class="header">
            <tr>
                <td style="width: 88px;" class="center">
                    @if($studentPhotoDataUri)
                        <img class="id-photo" src="{{ $studentPhotoDataUri }}" alt="Student Photo">
                    @endif
                </td>
                <td class="title-wrap">
                    <h1>{{ strtoupper($school?->name ?? 'SCHOOL NAME') }}</h1>
                    <p>{{ strtoupper($school?->location ?? 'SCHOOL LOCATION') }}</p>
                </td>
                <td style="width: 88px;" class="center">
                    @if($schoolLogoDataUri)
                        <img class="school-logo" src="{{ $schoolLogoDataUri }}" alt="School Logo">
                    @endif
                </td>
            </tr>
        </table>

        <div class="section-title">
            REPORT SHEET FOR {{ strtoupper($term?->name ?? '-') }} {{ strtoupper($session?->academic_year ?: $session?->session_name ?: '-') }} SESSION
        </div>

        <table class="meta">
            <tr>
                <th style="width: 18%;">NAME</th>
                <td style="width: 32%;">{{ strtoupper($studentUser?->name ?? '-') }}</td>
                <th style="width: 18%;">CLASS</th>
                <td style="width: 32%;">{{ strtoupper($class?->name ?? '-') }}</td>
            </tr>
            <tr>
                <th>SERIAL NO</th>
                <td>{{ strtoupper($studentUser?->username ?? '-') }}</td>
                <th>NEXT TERM BEGINS</th>
                <td>{{ $nextTermBeginLabel }}</td>
            </tr>
            <tr>
                <th>GENDER</th>
                <td>{{ strtoupper((string)($student?->sex ?? '-')) }}</td>
                <th>AVERAGE</th>
                <td>{{ number_format((float) $averageScore, 2) }}</td>
            </tr>
            <tr>
                <th>TIMES PRESENT</th>
                <td>{{ $timesPresent }}</td>
                <th>TIMES SCHOOL OPENED</th>
                <td>{{ $timesOpened }}</td>
            </tr>
            <tr>
                <th>TOTAL OBTAINED</th>
                <td>{{ $totalScore }}</td>
                <th>TOTAL OBTAINABLE</th>
                <td>{{ $totalObtainable }}</td>
            </tr>
        </table>

        <table class="scores" style="margin-top: 8px;">
            <thead>
                <tr>
                    <th style="width: 22%;">SUBJECT</th>
                    <th style="width: 7%;" class="center">CA</th>
                    <th style="width: 7%;" class="center">EXAM</th>
                    <th style="width: 7%;" class="center">TOTAL</th>
                    <th style="width: 7%;" class="center">MIN</th>
                    <th style="width: 7%;" class="center">MAX</th>
                    <th style="width: 10%;" class="center">CLASS AVE</th>
                    <th style="width: 8%;" class="center">POSITION</th>
                    <th style="width: 8%;" class="center">GRADE</th>
                    <th style="width: 17%;" class="center">REMARK</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ strtoupper($row['subject_name']) }}</td>
                        <td class="center">{{ $row['ca'] }}</td>
                        <td class="center">{{ $row['exam'] }}</td>
                        <td class="center">{{ $row['total'] }}</td>
                        <td class="center">{{ $row['min_score'] ?? 0 }}</td>
                        <td class="center">{{ $row['max_score'] ?? 0 }}</td>
                        <td class="center">{{ number_format((float) ($row['class_average'] ?? 0), 2) }}</td>
                        <td class="center">{{ $row['position_label'] ?? '-' }}</td>
                        <td class="center">{{ strtoupper($row['grade']) }}</td>
                        <td class="center">{{ strtoupper($row['remark'] ?? '-') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="center">No result data found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="grades-key">
            <strong>GRADES:</strong>
            A [70-100] |
            B [60-69] |
            C [50-59] |
            D [40-49] |
            E [30-39] |
            F [0-29]
        </div>

        <table class="psycho-grid">
            <tr>
                <td style="width: 74%;">
                    <table class="psycho">
                        <thead>
                            <tr>
                                <th style="width: 75%;">PSYCHOMOTOR</th>
                                <th style="width: 25%;">RATE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($behaviourTraits as $trait)
                                <tr>
                                    <td>{{ strtoupper($trait['label']) }}</td>
                                    <td class="center">{{ $trait['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
                <td style="width: 2%;"></td>
                <td style="width: 24%;">
                    <table class="key-rate small">
                        <thead>
                            <tr>
                                <th>KEY RATE</th>
                                <th>SET</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>EXCELLENT</td><td class="center">5</td></tr>
                            <tr><td>VERY GOOD</td><td class="center">4</td></tr>
                            <tr><td>SATISFACTORY</td><td class="center">3</td></tr>
                            <tr><td>POOR</td><td class="center">2</td></tr>
                            <tr><td>VERY POOR</td><td class="center">1</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <table class="comment" style="margin-top: 8px;">
            <tr>
                <th style="width: 22%;">School Head Name</th>
                <td>{{ strtoupper($school?->head_of_school_name ?: '-') }}</td>
            </tr>
            <tr>
                <th>School Head Comment</th>
                <td>{{ strtoupper($schoolHeadComment ?? '-') }}</td>
            </tr>
            <tr>
                <th>Class Teacher Name</th>
                <td>{{ strtoupper($classTeacher?->name ?? '-') }}</td>
            </tr>
            <tr>
                <th>Class Teacher Comment</th>
                <td>{{ strtoupper($teacherComment ?? '-') }}</td>
            </tr>
        </table>

        <div class="signature-box">
            <strong>School Head Signature:</strong><br>
            @if($headSignatureDataUri)
                <img class="signature" src="{{ $headSignatureDataUri }}" alt="Head Signature">
            @else
                <div class="signature-placeholder"></div>
            @endif
        </div>
    </div>
</div>
@if(!$embedded)
</body>
</html>
@endif
