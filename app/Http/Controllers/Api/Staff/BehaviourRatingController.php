<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\StudentBehaviourRating;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BehaviourRatingController extends Controller
{
  private function resolveCurrentContext(int $schoolId, int $staffUserId, ?int $classId = null, ?int $termId = null): array
  {
    $session = AcademicSession::where('school_id', $schoolId)
      ->where('status', 'current')
      ->first();

    if (!$session) {
      return ['session' => null, 'class' => null, 'term' => null, 'classes' => collect(), 'terms' => collect()];
    }

    $directClassIds = SchoolClass::query()
      ->where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->where('class_teacher_user_id', $staffUserId)
      ->pluck('id')
      ->map(fn($id) => (int) $id)
      ->values();

    $departmentScopeByClass = [];
    if (
      Schema::hasTable('class_departments')
      && Schema::hasColumn('class_departments', 'class_teacher_user_id')
    ) {
      $departmentRows = DB::table('class_departments')
        ->join('classes', 'classes.id', '=', 'class_departments.class_id')
        ->where('class_departments.school_id', $schoolId)
        ->where('classes.school_id', $schoolId)
        ->where('classes.academic_session_id', $session->id)
        ->where('class_departments.class_teacher_user_id', $staffUserId)
        ->get([
          'class_departments.class_id',
          'class_departments.id as department_id',
        ]);

      foreach ($departmentRows as $row) {
        $cid = (int) $row->class_id;
        $did = (int) $row->department_id;
        if ($cid < 1 || $did < 1) continue;
        $departmentScopeByClass[$cid] = $departmentScopeByClass[$cid] ?? [];
        $departmentScopeByClass[$cid][] = $did;
      }
      foreach ($departmentScopeByClass as $cid => $ids) {
        $departmentScopeByClass[$cid] = array_values(array_unique(array_map('intval', $ids)));
      }
    }

    $classIds = $directClassIds
      ->merge(array_keys($departmentScopeByClass))
      ->map(fn($id) => (int) $id)
      ->filter(fn($id) => $id > 0)
      ->unique()
      ->values();

    $classes = $classIds->isEmpty()
      ? collect()
      : SchoolClass::query()
          ->where('school_id', $schoolId)
          ->where('academic_session_id', $session->id)
          ->whereIn('id', $classIds->all())
          ->orderBy('level')
          ->orderBy('name')
          ->get(['id', 'name', 'level', 'academic_session_id']);

    $selectedClass = $classId ? $classes->firstWhere('id', $classId) : $classes->first();

    $terms = Term::where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->orderBy('id')
      ->get(['id', 'name', 'academic_session_id']);

    $selectedTerm = $termId ? $terms->firstWhere('id', $termId) : $terms->first();

    return [
      'session' => $session,
      'class' => $selectedClass,
      'term' => $selectedTerm,
      'classes' => $classes,
      'terms' => $terms,
      'direct_class_ids' => $directClassIds->all(),
      'department_scope_by_class' => $departmentScopeByClass,
    ];
  }

  private function hasDirectClassAccess(array $ctx, int $classId): bool
  {
    $ids = array_map('intval', $ctx['direct_class_ids'] ?? []);
    return in_array($classId, $ids, true);
  }

  private function departmentScopeForClass(array $ctx, int $classId): array
  {
    $scopes = $ctx['department_scope_by_class'] ?? [];
    $raw = $scopes[$classId] ?? $scopes[(string) $classId] ?? [];
    return array_values(array_unique(array_map('intval', (array) $raw)));
  }

  // GET /api/staff/behaviour-rating/status
  public function status(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $ctx = $this->resolveCurrentContext((int)$user->school_id, (int)$user->id);

    return response()->json([
      'data' => [
        'can_access' => (bool)$ctx['class'],
        'class_count' => $ctx['classes']->count(),
      ]
    ]);
  }

  // GET /api/staff/behaviour-rating?class_id=&term_id=
  public function index(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = (int)$user->school_id;
    $data = $request->validate([
      'class_id' => 'nullable|integer',
      'term_id' => 'nullable|integer',
    ]);

    $ctx = $this->resolveCurrentContext(
      $schoolId,
      (int)$user->id,
      $data['class_id'] ?? null,
      $data['term_id'] ?? null
    );

    if (!$ctx['session']) {
      return response()->json(['data' => null, 'message' => 'No current session'], 200);
    }

    if (!$ctx['class']) {
      return response()->json(['data' => null, 'message' => 'Only class teachers can access behaviour rating'], 403);
    }

    if (!$ctx['term']) {
      return response()->json(['data' => null, 'message' => 'No terms found for current session'], 422);
    }

    $enrollmentsQuery = Enrollment::query()
      ->where('class_id', $ctx['class']->id)
      ->where('term_id', $ctx['term']->id)
      ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
        $q->where('school_id', $schoolId);
      })
      ->join('students', 'students.id', '=', 'enrollments.student_id')
      ->join('users', 'users.id', '=', 'students.user_id')
      ->orderBy('users.name');

    if (
      !$this->hasDirectClassAccess($ctx, (int) $ctx['class']->id)
      && Schema::hasColumn('enrollments', 'department_id')
    ) {
      $departmentIds = $this->departmentScopeForClass($ctx, (int) $ctx['class']->id);
      if (empty($departmentIds)) {
        return response()->json(['data' => null, 'message' => 'Only class teachers can access behaviour rating'], 403);
      }
      $enrollmentsQuery->whereIn('enrollments.department_id', $departmentIds);
    }

    $enrollments = $enrollmentsQuery->get([
      'students.id as student_id',
      'users.name as student_name',
    ]);

    $ratings = StudentBehaviourRating::query()
      ->where('school_id', $schoolId)
      ->where('class_id', $ctx['class']->id)
      ->where('term_id', $ctx['term']->id)
      ->get()
      ->keyBy('student_id');

    $students = $enrollments->map(function ($s) use ($ratings) {
      $r = $ratings->get($s->student_id);
      return [
        'student_id' => (int)$s->student_id,
        'student_name' => $s->student_name,
        'handwriting' => (int)($r->handwriting ?? 0),
        'speech' => (int)($r->speech ?? 0),
        'attitude' => (int)($r->attitude ?? 0),
        'reading' => (int)($r->reading ?? 0),
        'punctuality' => (int)($r->punctuality ?? 0),
        'teamwork' => (int)($r->teamwork ?? 0),
        'self_control' => (int)($r->self_control ?? 0),
        'teacher_comment' => (string)($r->teacher_comment ?? ''),
      ];
    })->values();

    return response()->json([
      'data' => [
        'session' => [
          'id' => $ctx['session']->id,
          'session_name' => $ctx['session']->session_name,
          'academic_year' => $ctx['session']->academic_year,
        ],
        'classes' => $ctx['classes'],
        'terms' => $ctx['terms'],
        'selected_class_id' => (int)$ctx['class']->id,
        'selected_term_id' => (int)$ctx['term']->id,
        'students' => $students,
      ],
    ]);
  }

  // POST /api/staff/behaviour-rating
  public function save(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = (int)$user->school_id;

    $data = $request->validate([
      'class_id' => 'required|integer',
      'term_id' => 'required|integer',
      'rows' => 'required|array',
      'rows.*.student_id' => 'required|integer',
      'rows.*.handwriting' => 'required|integer|min:0|max:5',
      'rows.*.speech' => 'required|integer|min:0|max:5',
      'rows.*.attitude' => 'required|integer|min:0|max:5',
      'rows.*.reading' => 'required|integer|min:0|max:5',
      'rows.*.punctuality' => 'required|integer|min:0|max:5',
      'rows.*.teamwork' => 'required|integer|min:0|max:5',
      'rows.*.self_control' => 'required|integer|min:0|max:5',
      'rows.*.teacher_comment' => 'nullable|string|max:500',
    ]);

    $ctx = $this->resolveCurrentContext($schoolId, (int)$user->id, (int)$data['class_id'], (int)$data['term_id']);
    if (!$ctx['class']) {
      return response()->json(['message' => 'Only class teachers can save behaviour ratings'], 403);
    }
    if (!$ctx['term']) {
      return response()->json(['message' => 'Invalid term'], 422);
    }

    $validStudentIdsQuery = Enrollment::query()
      ->where('class_id', $ctx['class']->id)
      ->where('term_id', $ctx['term']->id)
      ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
        $q->where('school_id', $schoolId);
      });

    if (
      !$this->hasDirectClassAccess($ctx, (int) $ctx['class']->id)
      && Schema::hasColumn('enrollments', 'department_id')
    ) {
      $departmentIds = $this->departmentScopeForClass($ctx, (int) $ctx['class']->id);
      if (empty($departmentIds)) {
        return response()->json(['message' => 'Only class teachers can save behaviour ratings'], 403);
      }
      $validStudentIdsQuery->whereIn('department_id', $departmentIds);
    }

    $validStudentIds = $validStudentIdsQuery
      ->pluck('student_id')
      ->map(fn($id) => (int)$id)
      ->toArray();
    $validSet = array_flip($validStudentIds);

    DB::transaction(function () use ($data, $schoolId, $user, $ctx, $validSet) {
      foreach ($data['rows'] as $row) {
        $studentId = (int)$row['student_id'];
        if (!isset($validSet[$studentId])) continue;

        StudentBehaviourRating::updateOrCreate(
          [
            'school_id' => $schoolId,
            'class_id' => $ctx['class']->id,
            'term_id' => $ctx['term']->id,
            'student_id' => $studentId,
          ],
          [
            'handwriting' => (int)$row['handwriting'],
            'speech' => (int)$row['speech'],
            'attitude' => (int)$row['attitude'],
            'reading' => (int)$row['reading'],
            'punctuality' => (int)$row['punctuality'],
            'teamwork' => (int)$row['teamwork'],
            'self_control' => (int)$row['self_control'],
            'teacher_comment' => isset($row['teacher_comment']) ? (trim((string)$row['teacher_comment']) ?: null) : null,
            'set_by_user_id' => $user->id,
          ]
        );
      }
    });

    return response()->json(['message' => 'Behaviour ratings saved']);
  }
}
