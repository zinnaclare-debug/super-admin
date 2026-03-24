<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $title }}</title>
  <style>
    @page { margin: 18px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
    .head { margin-bottom: 10px; }
    .head h1 { margin: 0 0 4px; font-size: 16px; }
    .meta { margin: 0; font-size: 10px; color: #374151; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: top; }
    th { background: #f1f5f9; font-size: 10px; }
    .num { text-align: center; width: 45px; }
    .small { width: 52px; text-align: center; }
    .total { width: 60px; text-align: center; }
    .comment { width: 160px; font-size: 9px; line-height: 1.25; word-break: break-word; }
    .summary { width: 180px; font-size: 9px; line-height: 1.25; word-break: break-word; }
  </style>
</head>
<body>
  @php
    $normalizedTitle = strtolower((string) ($title ?? ''));
    $isTeacherReport = $normalizedTitle === 'teacher report';
    $isStudentReport = $normalizedTitle === 'student report';
    $emptyColspan = 10 + ($isTeacherReport ? 1 : 0) + ($isStudentReport ? 1 : 0);
  @endphp

  <div class="head">
    <h1>{{ $title }}</h1>
    <p class="meta"><strong>School:</strong> {{ $schoolName }}</p>
    <p class="meta">
      <strong>Session:</strong>
      {{ $context['current_session']['session_name'] ?? $context['current_session']['academic_year'] ?? '-' }}
      |
      <strong>Term:</strong> {{ $context['selected_term']['name'] ?? '-' }}
    </p>
  </div>

  <table>
    <thead>
      <tr>
        <th class="num">S/N</th>
        <th>Name</th>
        <th>Email</th>
        <th class="small">A</th>
        <th class="small">B</th>
        <th class="small">C</th>
        <th class="small">D</th>
        <th class="small">E</th>
        <th class="small">F</th>
        <th class="total">Total</th>
        @if($isTeacherReport)
          <th class="summary">Summary</th>
        @endif
        @if($isStudentReport)
          <th class="comment">Teacher Comment</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @forelse(($rows ?? []) as $row)
        <tr>
          <td class="num">{{ $row['sn'] ?? '-' }}</td>
          <td>{{ $row['name'] ?? '-' }}</td>
          <td>{{ $row['email'] ?? '-' }}</td>
          <td class="small">{{ $row['grades']['A'] ?? '-' }}</td>
          <td class="small">{{ $row['grades']['B'] ?? '-' }}</td>
          <td class="small">{{ $row['grades']['C'] ?? '-' }}</td>
          <td class="small">{{ $row['grades']['D'] ?? '-' }}</td>
          <td class="small">{{ $row['grades']['E'] ?? '-' }}</td>
          <td class="small">{{ $row['grades']['F'] ?? '-' }}</td>
          <td class="total">{{ $row['total_graded'] ?? '-' }}</td>
          @if($isTeacherReport)
            <td class="summary">{{ $row['summary'] ?? 'Completed' }}</td>
          @endif
          @if($isStudentReport)
            <td class="comment">{{ $row['teacher_comment'] ?? '-' }}</td>
          @endif
        </tr>
      @empty
        <tr>
          <td colspan="{{ $emptyColspan }}">No records found for this term.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
