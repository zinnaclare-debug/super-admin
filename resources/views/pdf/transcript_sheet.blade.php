<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Transcript</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #111827; }
        .sheet { position: relative; border: 1px solid #d1d5db; padding: 10px; }
        .watermark {
            position: fixed;
            top: 34%;
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
        .transcript-headline {
            margin: 4px 0 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
        }
        .session-title {
            border: 1px solid #111;
            border-bottom: 0;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            padding: 5px 6px;
            letter-spacing: 0.3px;
        }
        .session-page {
            margin-top: 10px;
        }
        .session-page.break {
            page-break-before: always;
        }
        .session-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .session-grid td { border: 0; padding: 0 4px; vertical-align: top; }
        .session-card { border: 1px solid #111; }
        .session-card table { margin-top: 0; }
        .session-card th,
        .session-card td {
            font-size: 7px;
            padding: 3px 4px;
        }
        .grade-key { margin-top: 10px; border: 1px solid #111; padding: 5px 6px; font-size: 8px; }
        .ratings-grid { margin-top: 6px; width: 100%; }
        .ratings-grid td { border: 0; padding: 0; vertical-align: top; }
        .signature-box { margin-top: 8px; border: 1px solid #111; padding: 6px; min-height: 58px; }
        .signature { width: 120px; height: 44px; object-fit: contain; border-bottom: 1px dashed #6b7280; }
        .signature-placeholder { width: 120px; height: 44px; border-bottom: 1px dashed #6b7280; }
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
    $groupPages = array_chunk((array) ($groups ?? []), 3);
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
                    <p class="transcript-headline">STUDENT TRANSCRIPT</p>
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

        @foreach($groupPages as $pageIndex => $groupPage)
            <div class="session-page {{ $pageIndex > 0 ? 'break' : '' }}">
                <table class="session-grid">
                    <tr>
                        @for($i = 0; $i < 3; $i++)
                            @php($group = $groupPage[$i] ?? null)
                            <td style="width: 33.33%;">
                                @if($group)
                                    @php
                                        $sessionName = strtoupper((string) (data_get($group, 'session.academic_year') ?: data_get($group, 'session.session_name', '-')));
                                        $className = strtoupper((string) data_get($group, 'class.name', '-'));
                                    @endphp
                                    <div class="session-card">
                                        <div class="session-title">
                                            SESSION: {{ $sessionName }} | CLASS: {{ $className }}
                                        </div>
                                        <table>
                                            <thead>
                                            <tr>
                                                <th style="width: 34%;">SUBJECT</th>
                                                <th style="width: 11%;" class="center">FIRST</th>
                                                <th style="width: 11%;" class="center">SECOND</th>
                                                <th style="width: 11%;" class="center">THIRD</th>
                                                <th style="width: 17%;" class="center">ANNUAL AVG</th>
                                                <th style="width: 16%;" class="center">ANNUAL GRADE</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse((array) ($group['rows'] ?? []) as $row)
                                                <tr>
                                                    <td>{{ strtoupper((string) ($row['subject_name'] ?? '-')) }}</td>
                                                    <td class="center">{{ $row['first_total'] === null ? '-' : (int) $row['first_total'] }}</td>
                                                    <td class="center">{{ $row['second_total'] === null ? '-' : (int) $row['second_total'] }}</td>
                                                    <td class="center">{{ $row['third_total'] === null ? '-' : (int) $row['third_total'] }}</td>
                                                    <td class="center">{{ $row['annual_average'] === null ? '-' : number_format((float) $row['annual_average'], 2) }}</td>
                                                    <td class="center">{{ strtoupper((string) ($row['annual_grade'] ?? '-')) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="center">No graded result.</td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                </table>
            </div>
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
