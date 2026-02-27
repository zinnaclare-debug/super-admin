<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassActivity;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClassActivitiesController extends Controller
{
  private function resolveCurrentTermId(int $schoolId, int $sessionId): ?int
  {
    if (Schema::hasColumn('terms', 'is_current')) {
      $current = Term::where('school_id', $schoolId)
        ->where('academic_session_id', $sessionId)
        ->where('is_current', true)
        ->first();
      if ($current) return (int)$current->id;
    }

    // Backward-compatible fallback for DBs that don't yet have is_current.
    $fallback = Term::where('school_id', $schoolId)
      ->where('academic_session_id', $sessionId)
      ->orderBy('id')
      ->first();

    return $fallback ? (int)$fallback->id : null;
  }

  private function currentSessionClassIds(int $schoolId, int $sessionId, int $studentId, int $currentTermId): array
  {
    $enrollQuery = Enrollment::query()
      ->join('classes', 'classes.id', '=', 'enrollments.class_id')
      ->where('classes.school_id', $schoolId)
      ->where('classes.academic_session_id', $sessionId)
      ->where('enrollments.student_id', $studentId)
      ->where('enrollments.term_id', $currentTermId)
      ->orderByDesc('enrollments.id');
    if (Schema::hasColumn('enrollments', 'school_id')) {
      $enrollQuery->where('enrollments.school_id', $schoolId);
    }

    $activeClassId = $enrollQuery->value('enrollments.class_id');
    if ($activeClassId) {
      return [(int) $activeClassId];
    }

    $classIds = DB::table('class_students')
      ->where('school_id', $schoolId)
      ->where('academic_session_id', $sessionId)
      ->where('student_id', $studentId)
      ->pluck('class_id')
      ->map(fn ($id) => (int) $id)
      ->filter(fn ($id) => $id > 0)
      ->unique()
      ->values()
      ->all();

    if (!empty($classIds)) {
      return [(int) $classIds[0]];
    }

    $legacyQuery = Enrollment::query()
      ->where('student_id', $studentId)
      ->where('term_id', $currentTermId)
      ->orderByDesc('id');
    if (Schema::hasColumn('enrollments', 'school_id')) {
      $legacyQuery->where('school_id', $schoolId);
    }

    $legacyClassId = $legacyQuery->value('class_id');
    return $legacyClassId ? [(int) $legacyClassId] : [];
  }

  private function allowedTermSubjectIds(Request $request): array
  {
    $subjectsRes = $this->mySubjects($request)->getData(true);
    return collect($subjectsRes['data'] ?? [])
      ->pluck('term_subject_id')
      ->map(fn($v) => (int)$v)
      ->toArray();
  }

  // GET /api/student/class-activities/subjects
  // list subjects student is enrolled in (current session)
  public function mySubjects(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'student', 403);

    $schoolId = $user->school_id;

    $session = AcademicSession::where('school_id', $schoolId)
      ->where('status', 'current')
      ->first();

    if (!$session) return response()->json(['data' => []]);

    $currentTermId = $this->resolveCurrentTermId((int)$schoolId, (int)$session->id);
    if (!$currentTermId) return response()->json(['data' => []]);

    $student = Student::where('user_id', $user->id)
      ->where('school_id', $schoolId)
      ->first();

    if (!$student) return response()->json(['data' => []]);

    $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id, $currentTermId);
    if (empty($classIds)) return response()->json(['data' => []]);

    $termSubjects = TermSubject::query()
      ->where('term_subjects.school_id', $schoolId)
      ->where('term_subjects.term_id', $currentTermId)
      ->whereIn('term_subjects.class_id', $classIds)
      ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->orderBy('subjects.name')
      ->get([
        'term_subjects.id as term_subject_id',
        'subjects.name as subject_name',
        'subjects.code as subject_code',
      ])
      ->unique('term_subject_id')
      ->values();

    return response()->json(['data' => $termSubjects]);
  }

  // GET /api/student/class-activities?term_subject_id=123&subject_id=12
  public function index(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'student', 403);

    $schoolId = $user->school_id;
    $data = $request->validate([
      'term_subject_id' => 'nullable|integer',
      'subject_id' => 'nullable|integer',
    ]);

    $allowed = $this->allowedTermSubjectIds($request);

    if (empty($allowed)) return response()->json(['data' => []]);

    if (!empty($data['term_subject_id']) && !in_array((int)$data['term_subject_id'], $allowed, true)) {
      return response()->json(['data' => []]); // or 403
    }

    $query = ClassActivity::query()
      ->join('term_subjects', 'term_subjects.id', '=', 'class_activities.term_subject_id')
      ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->where('class_activities.school_id', $schoolId)
      ->whereIn('class_activities.term_subject_id', $allowed);

    if (!empty($data['term_subject_id'])) {
      $query->where('class_activities.term_subject_id', (int)$data['term_subject_id']);
    }

    if (!empty($data['subject_id'])) {
      $query->where('term_subjects.subject_id', (int)$data['subject_id']);
    }

    $items = $query
      ->orderByDesc('class_activities.id')
      ->get([
        'class_activities.*',
        'subjects.id as subject_id',
        'subjects.name as subject_name',
        'subjects.code as subject_code',
        'classes.id as class_id',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.id as term_id',
        'terms.name as term_name',
      ])
      ->map(function ($a) {
      $a->file_url = Storage::disk('public')->url($a->file_path);
      return $a;
    });

    return response()->json(['data' => $items]);
  }

  // GET /api/student/class-activities/{activity}/download
  public function download(Request $request, ClassActivity $activity)
  {
    $user = $request->user();
    abort_unless($user->role === 'student', 403);
    abort_unless((int)$activity->school_id === (int)$user->school_id, 403);

    $allowed = $this->allowedTermSubjectIds($request);
    abort_unless(in_array((int)$activity->term_subject_id, $allowed, true), 403);

    if (!$activity->file_path || !Storage::disk('public')->exists($activity->file_path)) {
      return response()->json(['message' => 'File not found'], 404);
    }

    return Storage::disk('public')->download(
      $activity->file_path,
      $activity->original_name ?: basename($activity->file_path)
    );
  }
}
