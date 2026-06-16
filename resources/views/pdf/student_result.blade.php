@php($embedded = (bool) ($embedded ?? false))
@php
    $resultTemplate = \App\Support\ResultPdfTemplate::normalize($resultTemplate ?? []);
    $templatePrimaryColor = $resultTemplate['primary_color'] ?? '#111827';
    $templateAccentColor = $resultTemplate['accent_color'] ?? '#1d4ed8';
    $templateWatermarkOpacity = $resultTemplate['watermark_opacity'] ?? 0.07;
    $templateIsCompact = ($resultTemplate['layout'] ?? 'classic') === 'compact';
@endphp
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
            font-family: Arial, Helvetica, DejaVu Sans, sans-serif;
            font-size: {{ $templateIsCompact ? '6.5px' : '7px' }};
            color: {{ $templatePrimaryColor }};
        }
        .sheet {
            position: relative;
            border: 1px solid {{ $templateAccentColor }};
            padding: {{ $templateIsCompact ? '8px' : '10px' }};
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            top: 28%;
            left: 50%;
            width: 320px;
            height: 320px;
            margin-left: -160px;
            opacity: {{ $templateWatermarkOpacity }};
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
            border: 1px solid {{ $templatePrimaryColor }};
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
            border: 1px solid {{ $templatePrimaryColor }};
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
            border: 1px solid {{ $templatePrimaryColor }};
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
            border: 1px solid {{ $templatePrimaryColor }};
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
            border: 1px solid {{ $templatePrimaryColor }};
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
        .psycho-horizontal th,
        .psycho-horizontal td {
            text-align: left;
        }
        .psycho-horizontal th.rate,
        .psycho-horizontal td.rate {
            text-align: center;
        }
        .key-rating-line {
            margin-top: 0;
            border: 1px solid {{ $templatePrimaryColor }};
            border-top: 0;
            padding: 4px 6px;
            font-size: 10px;
            letter-spacing: 0.2px;
        }
        .footer-container {
            margin-top: 8px;
            border: 1px solid {{ $templatePrimaryColor }};
            padding: 6px;
        }
        .comment-layout {
            margin-top: 8px;
            width: 100%;
        }
        .comment-layout td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        .signature-panel {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-panel th,
        .signature-panel td {
            border: 1px solid {{ $templatePrimaryColor }};
            padding: 4px 6px;
        }
        .signature-panel th {
            background: #f3f4f6;
            text-align: left;
        }
        .signature-panel td {
            text-align: center;
        }
        .info-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .info-box:first-child {
            margin-top: 0;
        }
        .info-box th,
        .info-box td {
            border: 1px solid {{ $templatePrimaryColor }};
            padding: 5px 6px;
        }
        .info-box th {
            background: #f3f4f6;
            text-align: left;
            width: 28%;
        }
        .signature-only {
            border: 1px solid {{ $templatePrimaryColor }};
            min-height: 126px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            text-align: center;
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
    $isCumulative = (bool) ($isCumulativeResult ?? (($resultType ?? 'term') === 'cumulative'));
    $showStudentPhoto = (bool) ($resultTemplate['show_student_photo'] ?? true);
    $showSchoolLogo = (bool) ($resultTemplate['show_school_logo'] ?? true);
    $showWatermark = (bool) ($resultTemplate['show_watermark'] ?? true);
    $showAttendance = (bool) ($resultTemplate['show_attendance'] ?? true);
    $showBehaviour = (bool) ($resultTemplate['show_behaviour'] ?? true);
    $showSignature = (bool) ($resultTemplate['show_signature'] ?? true);
    $showThirdTermPreviousTotals = (bool) ($resultTemplate['show_third_term_previous_totals'] ?? false);
    $showCumulativeTermTotals = (bool) data_get($resultTemplate, 'cumulative.show_term_totals', true);
    $showCumulativeAverage = (bool) data_get($resultTemplate, 'cumulative.show_average', true);
    $assessmentSchema = \App\Support\AssessmentSchema::normalizeSchema($assessmentSchema ?? []);
    $activeCaIndices = \App\Support\AssessmentSchema::activeCaIndices($assessmentSchema);
    $assessmentParts = [];
    foreach ($activeCaIndices as $index) {
        $assessmentParts[] = 'CA' . ($index + 1) . ' (' . ((int) ($assessmentSchema['ca_maxes'][$index] ?? 0)) . ')';
    }
    $assessmentPattern = $isCumulative
        ? trim(($showCumulativeTermTotals ? 'FIRST TERM TOTAL | SECOND TERM TOTAL | THIRD TERM TOTAL' : '') . ($showCumulativeAverage ? ' | AVERAGE' : ''), ' |')
        : implode(' | ', $assessmentParts) . ' | EXAM (' . ((int) ($assessmentSchema['exam_max'] ?? 0)) . ')' . ($showThirdTermPreviousTotals ? ' | FIRST TERM TOTAL | SECOND TERM TOTAL | THIRD TERM TOTAL' : '');
    $scoreColspan = 3;
    if ($isCumulative) {
        $scoreColspan += $showCumulativeTermTotals ? 3 : 0;
        $scoreColspan += $showCumulativeAverage ? 1 : 0;
    } else {
        $scoreColspan += count($activeCaIndices);
        $scoreColspan += 2;
        $scoreColspan += $showThirdTermPreviousTotals ? 3 : 0;
    }
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
    @if($showWatermark && $schoolLogoDataUri)
        <img class="watermark" src="{{ $schoolLogoDataUri }}" alt="">
    @endif

    <div class="content">
        <table class="header">
            <tr>
                <td style="width: 88px;" class="center">
                    @if($showStudentPhoto && $studentPhotoDataUri)
                        <img class="id-photo" src="{{ $studentPhotoDataUri }}" alt="Student Photo">
                    @endif
                </td>
                <td class="title-wrap">
                    <h1>{{ strtoupper($school?->name ?? 'SCHOOL NAME') }}</h1>
                    <p>{{ strtoupper($school?->location ?? 'SCHOOL LOCATION') }}</p>
                </td>
                <td style="width: 88px;" class="center">
                    @if($showSchoolLogo && $schoolLogoDataUri)
                        <img class="school-logo" src="{{ $schoolLogoDataUri }}" alt="School Logo">
                    @endif
                </td>
            </tr>
        </table>

        <div class="section-title">
            {{ $isCumulative ? 'CUMULATIVE REPORT SHEET' : 'REPORT SHEET' }} FOR {{ strtoupper($term?->name ?? '-') }} {{ strtoupper($session?->academic_year ?: $session?->session_name ?: '-') }} SESSION
        </div>
        <div class="grades-key" style="margin-top: 0; border-top: 0;">
            <strong>ASSESSMENT PATTERN:</strong> {{ strtoupper($assessmentPattern) }}
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
                <td>{{ $averageDisplay ?? number_format((float) $averageScore, 2) }}</td>
            </tr>
            @if($showResultPosition ?? true)
                <tr>
                    <th>POSITION</th>
                    <td colspan="3">{{ $classPositionDisplay ?? '-' }}</td>
                </tr>
            @endif
            <tr>
                <th>{{ $showAttendance ? 'ATTENDANCE' : 'TERM' }}</th>
                <td>{{ $showAttendance ? ($timesPresent . '/' . $timesOpened) : strtoupper($term?->name ?? '-') }}</td>
                <th>TOTAL STUDENTS</th>
                <td>{{ $classSize ?? '-' }}</td>
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
                    <th class="center">SUBJECT</th>
                    @if($isCumulative)
                        @if($showCumulativeTermTotals)
                            <th class="center">FIRST TERM</th>
                            <th class="center">SECOND TERM</th>
                            <th class="center">THIRD TERM</th>
                        @endif
                        @if($showCumulativeAverage)
                            <th class="center">AVERAGE</th>
                        @endif
                    @else
                        @foreach($activeCaIndices as $index)
                            <th class="center">C{{ $index + 1 }} ({{ (int) ($assessmentSchema['ca_maxes'][$index] ?? 0) }})</th>
                        @endforeach
                        <th class="center">EXAM ({{ (int) ($assessmentSchema['exam_max'] ?? 0) }})</th>
                        <th class="center">TOTAL</th>
                        @if($showThirdTermPreviousTotals)
                            <th class="center">FIRST TERM</th>
                            <th class="center">SECOND TERM</th>
                            <th class="center">THIRD TERM</th>
                        @endif
                    @endif
                    <th class="center">GRADE</th>
                    <th class="center">REMARK</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ strtoupper($row['subject_name']) }}</td>
                        @if($isCumulative)
                            @if($showCumulativeTermTotals)
                                <td class="center">{{ $row['first_term_total'] ?? '-' }}</td>
                                <td class="center">{{ $row['second_term_total'] ?? '-' }}</td>
                                <td class="center">{{ $row['third_term_total'] ?? '-' }}</td>
                            @endif
                            @if($showCumulativeAverage)
                                <td class="center">{{ $row['average'] ?? '-' }}</td>
                            @endif
                        @else
                            @foreach($activeCaIndices as $index)
                                @php($caValue = $row['ca_breakdown'][$index] ?? null)
                                <td class="center">{{ ($caValue === null || $caValue === '') ? '-' : (int) $caValue }}</td>
                            @endforeach
                            <td class="center">{{ $row['exam'] }}</td>
                            <td class="center">{{ $row['total'] }}</td>
                            @if($showThirdTermPreviousTotals)
                                <td class="center">{{ $row['first_term_total'] ?? '-' }}</td>
                                <td class="center">{{ $row['second_term_total'] ?? '-' }}</td>
                                <td class="center">{{ $row['third_term_total'] ?? '-' }}</td>
                            @endif
                        @endif
                        <td class="center">{{ strtoupper($row['grade']) }}</td>
                        <td class="center">{{ strtoupper($row['remark'] ?? '-') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $scoreColspan }}" class="center">No result data found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="grades-key">
            <strong>GRADES:</strong>
            {{ \App\Support\GradingSchema::displayKey($school->grading_schema ?? null) }}
        </div>

        @if($showBehaviour)
            @php
                $traitPerRow = 5;
                $traitRows = collect($behaviourTraits ?? [])->values()->chunk($traitPerRow);
                if ($traitRows->isEmpty()) {
                    $traitRows = collect([collect()]);
                }
            @endphp
            <table class="psycho psycho-horizontal" style="margin-top: 8px;">
                <thead>
                    <tr>
                        @for($i = 0; $i < $traitPerRow; $i++)
                            <th style="width: 16%;">PSYCHOMOTOR</th>
                            <th class="rate" style="width: 4%;">RATE</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach($traitRows as $rowTraits)
                        <tr>
                            @for($i = 0; $i < $traitPerRow; $i++)
                                @php($trait = $rowTraits->get($i))
                                <td>{{ strtoupper((string)($trait['label'] ?? '')) }}</td>
                                <td class="rate">{{ $trait['value'] ?? '' }}</td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="key-rating-line">
                <strong>KEY RATING:</strong>
                5 - EXCELLENT
                &nbsp;&nbsp;&nbsp; 4 - VERY GOOD
                &nbsp;&nbsp;&nbsp; 3 - SATISFACTORY
                &nbsp;&nbsp;&nbsp; 2 - POOR
                &nbsp;&nbsp;&nbsp; 1 - VERY POOR
            </div>
        @endif

        <div class="footer-container">
            <table class="comment-layout" style="margin-top: 0;">
                <tr>
                    <td style="width: 74%;">
                        <table class="info-box">
                            <tr>
                                <th>Head of School Name</th>
                                <td>{{ strtoupper($school?->head_of_school_name ?: '-') }}</td>
                            </tr>
                        </table>
                        <table class="info-box">
                            <tr>
                                <th>Head of School Comment</th>
                                <td>{{ strtoupper($schoolHeadComment ?? '-') }}</td>
                            </tr>
                        </table>
                        <table class="info-box">
                            <tr>
                                <th>Class Teacher Name</th>
                                <td>{{ strtoupper($classTeacher?->name ?? '-') }}</td>
                            </tr>
                        </table>
                        <table class="info-box">
                            <tr>
                                <th>Class Teacher Comment</th>
                                <td>{{ strtoupper($teacherComment ?? '-') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 2%;"></td>
                    @if($showSignature)
                        <td style="width: 24%;">
                            <div class="signature-only">
                                @if($headSignatureDataUri)
                                    <img class="signature" src="{{ $headSignatureDataUri }}" alt="Head Signature">
                                @else
                                    <div class="signature-placeholder"></div>
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    </div>
</div>
@if(!$embedded)
</body>
</html>
@endif
