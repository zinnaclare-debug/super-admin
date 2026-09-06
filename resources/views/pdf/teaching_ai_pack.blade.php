<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 24px 28px; }
  body { font-family: DejaVu Sans, sans-serif; color: #14213d; font-size: 10px; line-height: 1.45; }
  .header { border-bottom: 4px solid {{ $school->primary_color ?: '#123b74' }}; padding-bottom: 10px; margin-bottom: 16px; }
  .brand { color: {{ $school->primary_color ?: '#123b74' }}; font-size: 18px; font-weight: 700; margin: 0; }
  .sub { color: {{ $school->accent_color ?: '#d97706' }}; font-size: 10px; font-weight: 700; margin-top: 3px; }
  .meta { background: #f5f8fc; border-left: 4px solid {{ $school->accent_color ?: '#d97706' }}; padding: 8px 10px; margin-bottom: 14px; }
  h1 { font-size: 16px; color: {{ $school->primary_color ?: '#123b74' }}; margin: 0 0 5px; }
  h2 { font-size: 12px; color: {{ $school->primary_color ?: '#123b74' }}; border-bottom: 1px solid #d8e2ee; padding-bottom: 3px; margin: 14px 0 6px; }
  h3 { font-size: 10px; margin: 8px 0 3px; color: {{ $school->accent_color ?: '#d97706' }}; }
  .question { page-break-inside: avoid; padding: 8px; margin: 7px 0; border: 1px solid #d8e2ee; background: #fbfdff; }
  .mark { float: right; font-weight: bold; color: {{ $school->primary_color ?: '#123b74' }}; }
  .label { font-weight: bold; color: {{ $school->primary_color ?: '#123b74' }}; }
  ul { margin: 4px 0 8px 18px; padding: 0; }
  .footer { margin-top: 20px; font-size: 8px; color: #667085; border-top: 1px solid #d8e2ee; padding-top: 6px; }
</style>
</head>
<body>
@php
  $asText = static function ($value): string {
    if (is_array($value)) {
      return collect($value)->flatten()->map(static fn ($item) => (string) $item)->filter()->implode('; ');
    }

    return (string) ($value ?? '');
  };
@endphp
<div class="header">
  <p class="brand">{{ $school->name }}</p>
  <p class="sub">AI Teaching Planner Draft | Review and edit before classroom use</p>
</div>
<div class="meta">
  <strong>Subject:</strong> {{ $subject->subject_name }} &nbsp; | &nbsp;
  <strong>Class:</strong> {{ $subject->class_name }} ({{ $subject->class_level }}) &nbsp; | &nbsp;
  <strong>Session:</strong> {{ optional($job->created_at)->format('Y') ? $job->academic_session_id : '-' }} &nbsp; | &nbsp;
  <strong>Generated:</strong> {{ optional($job->created_at)->format('d M Y, h:i A') }}
</div>

@if($document === 'exam_questions')
  <h1>Examination Questions</h1>
  @foreach(($content['exam_questions'] ?? []) as $index => $question)
    <div class="question">
      <span class="mark">{{ $asText($question['marks'] ?? 5) }} marks</span>
      <strong>{{ $index + 1 }}. {{ $asText($question['question'] ?? '') }}</strong>
      <h3>Marking Guide</h3>
      <p>{{ $asText($question['answer_guide'] ?? '') }}</p>
    </div>
  @endforeach
@endif

@if($document === 'lesson_notes')
  <h1>Lesson Notes</h1>
  @foreach(($content['lesson_notes'] ?? []) as $note)
    <h2>{{ $asText($note['topic'] ?? 'Topic') }}</h2>
    <p class="label">Learning Objectives</p>
    <ul>@foreach(($note['objectives'] ?? []) as $objective)<li>{{ $asText($objective) }}</li>@endforeach</ul>
    <p><span class="label">Lesson Content:</span> {{ $asText($note['content'] ?? '') }}</p>
    <p><span class="label">Class Activities:</span> {{ $asText($note['activities'] ?? '') }}</p>
    <p><span class="label">Assessment:</span> {{ $asText($note['assessment'] ?? '') }}</p>
    <p><span class="label">Homework:</span> {{ $asText($note['homework'] ?? '') }}</p>
    <p><span class="label">References:</span> {{ $asText($note['references'] ?? '') }}</p>
  @endforeach
@endif

@if($document === 'lesson_plan')
  <h1>Lesson Plan</h1>
  @foreach(($content['lesson_plan'] ?? []) as $plan)
    <h2>{{ $asText($plan['week'] ?? 'Week') }}: {{ $asText($plan['topic'] ?? 'Topic') }}</h2>
    <p><span class="label">Duration:</span> {{ $asText($plan['duration'] ?? '') }}</p>
    <p class="label">Objectives</p>
    <ul>@foreach(($plan['objectives'] ?? []) as $objective)<li>{{ $asText($objective) }}</li>@endforeach</ul>
    <p class="label">Resources</p>
    <ul>@foreach(($plan['resources'] ?? []) as $resource)<li>{{ $asText($resource) }}</li>@endforeach</ul>
    <p><span class="label">Introduction:</span> {{ $asText($plan['introduction'] ?? '') }}</p>
    <p><span class="label">Teacher Activities:</span> {{ $asText($plan['teacher_activities'] ?? '') }}</p>
    <p><span class="label">Learner Activities:</span> {{ $asText($plan['learner_activities'] ?? '') }}</p>
    <p><span class="label">Assessment:</span> {{ $asText($plan['assessment'] ?? '') }}</p>
    <p><span class="label">Conclusion:</span> {{ $asText($plan['conclusion'] ?? '') }}</p>
  @endforeach
@endif

<div class="footer">AI-generated draft. Teacher review, curriculum verification, and school approval are required before use.</div>
</body>
</html>
