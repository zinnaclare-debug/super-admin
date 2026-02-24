<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Transcript</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #111827; }
        .sheet { position: relative; border: 1px solid #d1d5db; padding: 10px; overflow: hidden; }
        .watermark {
            position: absolute;
            top: 30%;
            left: 50%;
            width: 300px;
            height: 300px;
            margin-left: -150px;
            opacity: 0.06;
            z-index: 0;
            object-fit: contain;
        }
        .content { position: relative; z-index: 1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 4px 5px; vertical-align: middle; }
        th { background: #f3f4f6; text-align: left; }
        .center { text-align: center; }
        .top-header td { border: 1px solid #111; }
        .logo, .photo { width: 72px; height: 72px; border: 1px solid #111; object-fit: cover; }
        .school-wrap { text-align: center; }
        .school-wrap h1 { margin: 0; font-size: 18px; letter-spacing: 0.5px; }
        .school-wrap p { margin: 3px 0 0; font-size: 10px; }
        .term-title {
            margin-top: 10px;
            border: 1px solid #111;
            border-bottom: 0;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            padding: 5px 6px;
            letter-spacing: 0.4px;
        }
        .grade-box td, .grade-box th { text-align: center; }
        .grade-key { margin-top: 10px; border: 1px solid #111; padding: 5px 6px; font-size: 8px; }
        .ratings-grid { margin-top: 6px; width: 100%; }
        .ratings-grid td { border: 0; padding: 0; vertical-align: top; }
        .signature-box { margin-top: 8px; border: 1px solid #111; padding: 6px; min-height: 58px; }
        .signature { width: 120px; height: 44px; object-fit: contain; border-bottom: 1px dashed #6b7280; }
        .signature-placeholder { width: 120px; height: 44px; border-bottom: 1px dashed #6b7280; }
        .term-gap { height: 12px; }
    </style>
</head>
<body>
@php
    $schoolName = strtoupper((string) ($school?->name ?? 'SCHOOL NAME'));
    $schoolAddress = strtoupper((string) ($school?->location ?? 'SCHOOL ADDRESS'));
    $studentName = strtoupper((string) ($studentUser?->name ?? '-'));
    $studentEmail = (string) ($studentUser?->email ?? '-');
    $studentSerial = strtoupper((string) ($studentUser?->username ?? '-'));
    $headName = strtoupper((string) ($school?->head_of_school_name ?: '-'));
@endphp

<div class="sheet">
    @if(!empty($schoolLogoDataUri))
        <img class="watermark" src="{{ $schoolLogoDataUri }}" alt="">
    @endif

    <div class="content">
        <table class="top-header">
            <tr>
                <td style="width: 82px;" class="center">
                    @if(!empty($studentPhotoDataUri))
                        <img class="photo" src="{{ $studentPhotoDataUri }}" alt="Student Photo">
                    @endif
                </td>
                <td class="school-wrap">
                    <h1>{{ $schoolName }}</h1>
                    <p>{{ $schoolAddress }}</p>
                </td>
                <td style="width: 82px;" class="center">
                    @if(!empty($schoolLogoDataUri))
                        <img class="logo" src="{{ $schoolLogoDataUri }}" alt="School Logo">
                    @endif
                </td>
            </tr>
        </table>

        <table style="margin-top: 8px;">
            <tr>
                <th style="width: 16%;">STUDENT NAME</th>
                <td style="width: 34%;">{{ $studentName }}</td>
                <th style="width: 16%;">SERIAL NO</th>
                <td style="width: 34%;">{{ $studentSerial }}</td>
            </tr>
            <tr>
                <th>EMAIL</th>
                <td colspan="3">{{ $studentEmail }}</td>
            </tr>
        </table>

        @foreach($entries as $entry)
            @php
                $rows = (array) ($entry['rows'] ?? []);
                $gradedRows = array_values(array_filter($rows, fn($r) => !empty($r['has_result'])));
                $termName = strtoupper((string) data_get($entry, 'term.name', '-'));
                $className = strtoupper((string) data_get($entry, 'class.name', '-'));
                $sessionName = strtoupper((string) (data_get($entry, 'session.academic_year') ?: data_get($entry, 'session.session_name', '-')));
                $totalScore = (int) data_get($entry, 'summary.total_score', 0);
                $averageScore = number_format((float) data_get($entry, 'summary.average_score', 0), 2);
                $overallGrade = strtoupper((string) data_get($entry, 'summary.overall_grade', '-'));
            @endphp

            @if(!empty($gradedRows))
                <div class="term-title">TRANSCRIPT FOR {{ $termName }}</div>
                <table>
                    <tr>
                        <th style="width: 18%;">SESSION</th>
                        <td style="width: 32%;">{{ $sessionName }}</td>
                        <th style="width: 18%;">CLASS</th>
                        <td style="width: 32%;">{{ $className }}</td>
                    </tr>
                </table>

                <table>
                    <thead>
                    <tr>
                        <th style="width: 24%;">SUBJECT</th>
                        <th style="width: 8%;" class="center">CA</th>
                        <th style="width: 8%;" class="center">EXAM</th>
                        <th style="width: 8%;" class="center">TOTAL</th>
                        <th style="width: 8%;" class="center">MIN</th>
                        <th style="width: 8%;" class="center">MAX</th>
                        <th style="width: 10%;" class="center">CLASS AVE</th>
                        <th style="width: 8%;" class="center">POS</th>
                        <th style="width: 8%;" class="center">GRADE</th>
                        <th style="width: 18%;" class="center">REMARK</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        @php $hasResult = (bool) ($row['has_result'] ?? false); @endphp
                        <tr>
                            <td>{{ strtoupper((string) ($row['subject_name'] ?? '-')) }}</td>
                            <td class="center">{{ $hasResult ? (int) ($row['ca'] ?? 0) : '-' }}</td>
                            <td class="center">{{ $hasResult ? (int) ($row['exam'] ?? 0) : '-' }}</td>
                            <td class="center">{{ $hasResult ? (int) ($row['total'] ?? 0) : '-' }}</td>
                            <td class="center">{{ $hasResult ? (int) ($row['min_score'] ?? 0) : '-' }}</td>
                            <td class="center">{{ $hasResult ? (int) ($row['max_score'] ?? 0) : '-' }}</td>
                            <td class="center">{{ $hasResult ? number_format((float) ($row['class_average'] ?? 0), 2) : '-' }}</td>
                            <td class="center">{{ $hasResult ? ($row['position_label'] ?? '-') : '-' }}</td>
                            <td class="center">{{ $hasResult ? strtoupper((string) ($row['grade'] ?? '-')) : '-' }}</td>
                            <td class="center">{{ $hasResult ? strtoupper((string) ($row['remark'] ?? '-')) : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <table class="grade-box" style="margin-top: 5px;">
                    <tr>
                        <th style="width: 33%;">TOTAL SCORE</th>
                        <th style="width: 33%;">AVERAGE SCORE</th>
                        <th style="width: 34%;">OVERALL GRADE</th>
                    </tr>
                    <tr>
                        <td>{{ $totalScore }}</td>
                        <td>{{ $averageScore }}</td>
                        <td>{{ $overallGrade }}</td>
                    </tr>
                </table>

                @if(!$loop->last)
                    <div class="term-gap"></div>
                @endif
            @endif
        @endforeach

        <div class="grade-key">
            <strong>GRADE KEY:</strong>
            A [70-100] |
            B [60-69] |
            C [50-59] |
            D [40-49] |
            E [30-39] |
            F [0-29]
        </div>

        <table class="ratings-grid">
            <tr>
                <td style="width: 74%;">
                    <table>
                        <thead>
                        <tr>
                            <th style="width: 75%;">BEHAVIOUR</th>
                            <th style="width: 25%;">RATE</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse((array) $behaviourTraits as $trait)
                            <tr>
                                <td>{{ strtoupper((string) ($trait['label'] ?? '-')) }}</td>
                                <td class="center">{{ (int) ($trait['value'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td>NO BEHAVIOUR RATING</td>
                                <td class="center">-</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </td>
                <td style="width: 2%;"></td>
                <td style="width: 24%;">
                    <table>
                        <thead>
                        <tr>
                            <th>RATE KEY</th>
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

        <table style="margin-top: 8px;">
            <tr>
                <th style="width: 24%;">HEAD OF SCHOOL NAME</th>
                <td>{{ $headName }}</td>
            </tr>
            <tr>
                <th>HEAD OF SCHOOL COMMENT</th>
                <td>{{ strtoupper((string) ($schoolHeadComment ?: '-')) }}</td>
            </tr>
        </table>

        <div class="signature-box">
            <strong>HEAD OF SCHOOL SIGNATURE:</strong><br>
            @if(!empty($headSignatureDataUri))
                <img class="signature" src="{{ $headSignatureDataUri }}" alt="Head Signature">
            @else
                <div class="signature-placeholder"></div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
