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
    th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; }
    th { background: #f1f5f9; font-size: 10px; }
    .num { text-align: center; width: 45px; }
    .small { width: 52px; text-align: center; }
    .total { width: 60px; text-align: center; }
  </style>
</head>
<body>
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
      </tr>
    </thead>
    <tbody>
      @forelse(($rows ?? []) as $row)
        <tr>
          <td class="num">{{ $row['sn'] ?? '-' }}</td>
          <td>{{ $row['name'] ?? '-' }}</td>
          <td>{{ $row['email'] ?? '-' }}</td>
          <td class="small">{{ $row['grades']['A'] ?? 0 }}</td>
          <td class="small">{{ $row['grades']['B'] ?? 0 }}</td>
          <td class="small">{{ $row['grades']['C'] ?? 0 }}</td>
          <td class="small">{{ $row['grades']['D'] ?? 0 }}</td>
          <td class="small">{{ $row['grades']['E'] ?? 0 }}</td>
          <td class="small">{{ $row['grades']['F'] ?? 0 }}</td>
          <td class="total">{{ $row['total_graded'] ?? 0 }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="10">No records found for this term.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
