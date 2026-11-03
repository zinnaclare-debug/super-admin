@php
    $template = \App\Support\ResultPdfTemplate::normalize($resultTemplate ?? []);
    $primary = $template['primary_color'] ?? '#17365D';
    $accent = $template['accent_color'] ?? '#2563EB';
    $isCumulative = (bool) ($isCumulativeResult ?? (($resultType ?? 'term') === 'cumulative'));
    $schema = \App\Support\AssessmentSchema::normalizeSchema($assessmentSchema ?? []);
    $caIndices = \App\Support\AssessmentSchema::activeCaIndices($schema);
    $showPrevious = (bool) ($template['show_third_term_previous_totals'] ?? false);
    $showTermTotals = (bool) data_get($template, 'cumulative.show_term_totals', true);
    $showAverage = (bool) data_get($template, 'cumulative.show_average', true);
    $showPhoto = (bool) ($template['show_student_photo'] ?? true);
    $showLogo = (bool) ($template['show_school_logo'] ?? true);
    $showAttendance = (bool) ($template['show_attendance'] ?? true);
    $showBehaviour = (bool) ($template['show_behaviour'] ?? true);
    $showSignature = (bool) ($template['show_signature'] ?? true);
    $showPosition = (bool) ($showResultPosition ?? false);
    $sessionLabel = strtoupper($session?->academic_year ?: $session?->session_name ?: '-');
    $daysPresent = (int) ($attendance?->days_present ?? 0);
    $schoolDays = (int) ($attendanceSetting?->total_school_days ?? 0);
    $nextTerm = '-';
    if (!empty($nextTermBeginDate)) {
        try { $nextTerm = \Carbon\Carbon::parse($nextTermBeginDate)->format('j M Y'); } catch (\Throwable $error) { $nextTerm = '-'; }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Classic Student Result</title>
<style>
@page { size: A4 landscape; margin: 8mm; }
body { font-family: DejaVu Sans, Arial, sans-serif; color: #152238; font-size: 8.5px; }
.sheet { border: 2px solid {{ $primary }}; padding: 7px; }
table { width: 100%; border-collapse: collapse; }
.header { border-bottom: 3px solid {{ $accent }}; margin-bottom: 6px; }
.header td { vertical-align: middle; padding: 3px; }
.logo, .photo { width: 67px; height: 67px; object-fit: contain; border: 1px solid {{ $primary }}; }
.photo { object-fit: cover; }
.school-name { color: {{ $primary }}; font-size: 19px; font-weight: bold; line-height: 1.15; }
.school-location { font-size: 9px; margin-top: 3px; }
.report-title { margin: 6px 0; padding: 6px; background: {{ $primary }}; color: #fff; font-size: 11px; font-weight: bold; text-align: center; letter-spacing: .3px; }
.info td, .info th, .scores td, .scores th, .traits td, .traits th { border: 1px solid #758399; padding: 4px; }
.info th { width: 15%; background: #E8EFF7; text-align: left; color: {{ $primary }}; }
.info td { width: 18.33%; }
.scores { margin-top: 7px; font-size: 7.5px; table-layout: fixed; }
.scores th { background: {{ $primary }}; color: #fff; text-align: center; font-size: 6.6px; line-height: 1.2; overflow-wrap: anywhere; }
.scores td { text-align: center; }
.scores td:first-child { text-align: left; font-weight: bold; width: 18%; }
.section { margin-top: 7px; border: 1px solid {{ $primary }}; }
.section-title { background: #E8EFF7; color: {{ $primary }}; font-weight: bold; padding: 4px 6px; }
.grade-key, .comments { padding: 5px 6px; line-height: 1.5; }
.traits { margin-top: 0; }
.traits th { background: #F4F7FB; text-align: left; }
.comment-grid td { width: 50%; vertical-align: top; padding: 4px; }
.comment-card { min-height: 47px; border: 1px solid #758399; padding: 5px; }
.comment-card strong { color: {{ $primary }}; }
.signatures td { width: 50%; text-align: center; padding-top: 14px; vertical-align: bottom; }
.signature { max-width: 112px; max-height: 35px; object-fit: contain; }
.signature-line { border-top: 1px solid #475569; margin: 7px auto 2px; width: 78%; }
.center { text-align: center; }
</style></head>
<body><div class="sheet">
<table class="header"><tr>
<td style="width:76px" class="center">@if($showLogo && $schoolLogoDataUri)<img class="logo" src="{{ $schoolLogoDataUri }}">@endif</td>
<td class="center"><div class="school-name">{{ strtoupper($school?->name ?? 'SCHOOL NAME') }}</div><div class="school-location">{{ strtoupper($school?->location ?? '') }}</div><div>{{ $isCumulative ? 'CUMULATIVE ACADEMIC REPORT' : 'STUDENT PROGRESS REPORT' }}</div></td>
<td style="width:76px" class="center">@if($showPhoto && $studentPhotoDataUri)<img class="photo" src="{{ $studentPhotoDataUri }}">@endif</td>
</tr></table>
<div class="report-title">{{ strtoupper($term?->name ?? '-') }} TERM, {{ $sessionLabel }} ACADEMIC SESSION</div>
<table class="info">
<tr><th>STUDENT NAME</th><td>{{ strtoupper($studentUser?->name ?? '-') }}</td><th>ADMISSION NO.</th><td>{{ strtoupper($studentUser?->username ?? '-') }}</td><th>CLASS</th><td>{{ strtoupper($class?->name ?? '-') }}</td></tr>
<tr><th>GENDER</th><td>{{ strtoupper($student?->sex ?? '-') }}</td><th>ATTENDANCE</th><td>{{ $showAttendance ? ($daysPresent . '/' . $schoolDays) : '-' }}</td><th>NEXT TERM</th><td>{{ $nextTerm }}</td></tr>
<tr><th>AVERAGE</th><td>{{ $averageDisplay ?? number_format((float) $averageScore, 2) }}</td><th>POSITION</th><td>{{ $showPosition ? ($classPositionDisplay ?? '-') : '-' }}</td><th>CLASS SIZE</th><td>{{ $classSize ?? '-' }}</td></tr>
</table>
<table class="scores"><thead>
@if($isCumulative)
<tr><th>LEARNING AREA / SUBJECT</th>
@if($showTermTotals)<th>FIRST TERM</th><th>SECOND TERM</th><th>THIRD TERM</th>@endif
@if($showAverage)<th>AVERAGE</th>@endif
<th>GRADE</th><th>REMARK</th></tr></thead><tbody>
@else
<tr>
<th rowspan="2">LEARNING AREA / SUBJECT</th>
@if(count($caIndices) > 0)<th colspan="{{ count($caIndices) }}">CONTINUOUS ASSESSMENT</th>@endif
<th rowspan="2">EXAM</th><th colspan="5">PSYCHOMOTOR / AFFECTIVE 10%</th><th colspan="2">QUIZ / NOTE COPYING</th>
@if($showPrevious)<th rowspan="2">1ST TOTAL</th><th rowspan="2">2ND TOTAL</th><th rowspan="2">TERM TOTAL</th><th rowspan="2">AVERAGE</th>@else<th rowspan="2">TOTAL</th>@endif
<th rowspan="2">GRADE</th><th rowspan="2">REMARK</th>
</tr>
<tr>@foreach($caIndices as $index)<th>{{ strtoupper($schema['ca_labels'][$index] ?? ('CA'.($index + 1))) }}</th>@endforeach<th>P1</th><th>P2</th><th>P3</th><th>P4</th><th>P5</th><th>QUIZ</th><th>NOTE</th></tr>
</thead><tbody>
@endif
@forelse($rows as $row)<tr><td>{{ strtoupper($row['subject_name'] ?? '-') }}</td>
@if($isCumulative)
@if($showTermTotals)<td>{{ $row['first_term_total'] ?? '-' }}</td><td>{{ $row['second_term_total'] ?? '-' }}</td><td>{{ $row['third_term_total'] ?? '-' }}</td>@endif
@if($showAverage)<td>{{ $row['average'] ?? '-' }}</td>@endif
@else
@foreach($caIndices as $index)<td>{{ ($row['ca_breakdown'][$index] ?? '') === '' ? '-' : ($row['ca_breakdown'][$index] ?? '-') }}</td>@endforeach
<td>{{ $row['exam'] ?? '-' }}</td>
@php($classicComponents = \App\Support\ClassicResultComponents::normalize($row['classic_components'] ?? null))
@foreach($classicComponents['psychomotor'] as $component)<td>{{ $component }}</td>@endforeach
<td>{{ $classicComponents['quiz'] }}</td><td>{{ $classicComponents['note_copying'] }}</td>
@if($showPrevious)<td>{{ $row['first_term_total'] ?? '-' }}</td><td>{{ $row['second_term_total'] ?? '-' }}</td><td>{{ $row['combined_total_score'] ?? '-' }}</td><td>{{ $row['combined_average'] ?? '-' }}</td>@else<td>{{ $row['total'] ?? '-' }}</td>@endif
@endif
<td>{{ strtoupper($showPrevious ? ($row['third_term_combined_grade'] ?? $row['grade'] ?? '-') : ($row['grade'] ?? '-')) }}</td><td>{{ strtoupper($showPrevious ? ($row['third_term_combined_remark'] ?? $row['remark'] ?? '-') : ($row['remark'] ?? '-')) }}</td></tr>
@empty<tr><td colspan="20" class="center">No result data found.</td></tr>@endforelse
</tbody></table>
<div class="section"><div class="section-title">GRADE INTERPRETATION</div><div class="grade-key">{{ \App\Support\GradingSchema::displayKey($school->grading_schema ?? null) }}</div></div>
@if($showBehaviour)<div class="section"><div class="section-title">GENERAL BEHAVIOUR / PSYCHOMOTOR ASSESSMENT</div><table class="traits">@foreach(collect($behaviourTraits ?? [])->chunk(5) as $traitChunk)<tr>@foreach($traitChunk as $trait)<th>{{ strtoupper($trait['label'] ?? '') }}</th><td>{{ $trait['value'] ?? '-' }}</td>@endforeach</tr>@endforeach</table></div>@endif
<div class="section"><div class="section-title">COMMENTS</div><table class="comment-grid"><tr><td><div class="comment-card"><strong>CLASS TEACHER: {{ strtoupper($classTeacher?->name ?? '-') }}</strong><br>{{ strtoupper($teacherComment ?? '-') }}</div></td><td><div class="comment-card"><strong>HEAD OF SCHOOL: {{ strtoupper($school?->head_of_school_name ?? '-') }}</strong><br>{{ strtoupper($schoolHeadComment ?? '-') }}</div></td></tr></table></div>
<table class="signatures"><tr><td><div class="signature-line"></div>Class Teacher's Signature</td><td>@if($showSignature && $headSignatureDataUri)<img class="signature" src="{{ $headSignatureDataUri }}">@endif<div class="signature-line"></div>Head of School's Signature</td></tr></table>
</div></body></html>
