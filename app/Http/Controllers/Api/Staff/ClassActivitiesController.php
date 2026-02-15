<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassActivity;
use App\Models\Term;
use App\Models\TermSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClassActivitiesController extends Controller
{
  // Backward-compatible alias for route naming parity with e-library.
  public function assignedSubjects(Request $request)
  {
    return $this->myAssignedSubjects($request);
  }

  private function hasTeacherAssignmentColumn(): bool
  {
    return Schema::hasColumn('term_subjects', 'teacher_user_id');
  }

  // GET /api/staff/class-activities/subjects
  // subjects this teacher is assigned to (current session)
  public function myAssignedSubjects(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    if (!$this->hasTeacherAssignmentColumn()) {
      return response()->json([
        'message' => 'Teacher assignment schema is missing. Run database migrations and try again.'
      ], 500);
    }

    $session = AcademicSession::where('school_id', $schoolId)
      ->where('status', 'current')
      ->first();

    if (!$session) return response()->json(['data' => []]);

    $currentTerm = Term::where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->where('is_current', true)
      ->first();

    if (!$currentTerm) return response()->json(['data' => []]);

    $rows = TermSubject::query()
      ->where('term_subjects.school_id', $schoolId)
      ->where('term_subjects.teacher_user_id', $user->id)
      ->where('term_subjects.term_id', $currentTerm->id)
      ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->where('terms.academic_session_id', $session->id)
      ->where('classes.academic_session_id', $session->id)
      ->orderBy('classes.level')
      ->orderBy('classes.name')
      ->orderBy('terms.id')
      ->orderBy('subjects.name')
      ->get([
        'term_subjects.id as term_subject_id',
        'subjects.name as subject_name',
        'subjects.code as subject_code',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.name as term_name',
      ]);

    return response()->json(['data' => $rows]);
  }

  // GET /api/staff/class-activities?term_subject_id=123
  // teacher sees only their own uploads (optionally filter by term_subject/subject)
  public function index(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;
    $data = $request->validate([
      'term_subject_id' => 'nullable|integer',
      'subject_id' => 'nullable|integer',
    ]);

    $query = ClassActivity::query()
      ->join('term_subjects', 'term_subjects.id', '=', 'class_activities.term_subject_id')
      ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->where('class_activities.school_id', $schoolId)
      ->where('class_activities.uploaded_by_user_id', $user->id);

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;
    if (!$currentTerm) {
      return response()->json(['data' => []]);
    }
    $query->where('term_subjects.term_id', $currentTerm->id);

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

  // POST /api/staff/class-activities
  // upload only to a term_subject assigned to this teacher (current session)
  public function upload(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    if (!$this->hasTeacherAssignmentColumn()) {
      return response()->json([
        'message' => 'Teacher assignment schema is missing. Run database migrations and try again.'
      ], 500);
    }

    $data = $request->validate([
      'term_subject_id' => 'required|integer',
      'title' => 'required|string|max:150',
      'description' => 'nullable|string|max:1000',
      'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png|max:20480',
    ]);

    $session = AcademicSession::where('school_id', $schoolId)
      ->where('status', 'current')
      ->first();

    if (!$session) return response()->json(['message' => 'No current session'], 422);

    $currentTerm = Term::where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->where('is_current', true)
      ->first();

    if (!$currentTerm) return response()->json(['message' => 'No current term'], 422);

    // ✅ term_subject must belong to same school AND assigned to this teacher
    $ts = TermSubject::where('id', $data['term_subject_id'])
      ->where('school_id', $schoolId)
      ->where('teacher_user_id', $user->id)
      ->first();

    if (!$ts) {
      return response()->json(['message' => 'You are not assigned to this subject'], 403);
    }

    // ✅ ensure term_subject is in current session
    $term = Term::where('id', $ts->term_id)
      ->where('school_id', $schoolId)
      ->first();

    if (
      !$term ||
      (int)$term->academic_session_id !== (int)$session->id ||
      (int)$term->id !== (int)$currentTerm->id
    ) {
      return response()->json(['message' => 'Upload allowed only for current session and current term'], 403);
    }

    $file = $request->file('file');
    $dir = "schools/{$schoolId}/class-activities";
    $path = $file->store($dir, 'public');

    $activity = ClassActivity::create([
      'school_id' => $schoolId,
      'uploaded_by_user_id' => $user->id,
      'term_subject_id' => $ts->id,
      'title' => $data['title'],
      'description' => $data['description'] ?? null,
      'file_path' => $path,
      'original_name' => $file->getClientOriginalName(),
      'mime_type' => $file->getClientMimeType(),
      'size' => $file->getSize(),
    ]);

    return response()->json([
      'message' => 'Uploaded',
      'data' => [
        'id' => $activity->id,
        'file_url' => Storage::disk('public')->url($activity->file_path),
      ]
    ], 201);
  }

  // DELETE /api/staff/class-activities/{activity}
  // only owner can delete (multi-tenant safe)
  public function destroy(Request $request, ClassActivity $activity)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    abort_unless((int)$activity->school_id === (int)$schoolId, 403);
    abort_unless((int)$activity->uploaded_by_user_id === (int)$user->id, 403);

    if ($activity->file_path && Storage::disk('public')->exists($activity->file_path)) {
      Storage::disk('public')->delete($activity->file_path);
    }

    $activity->delete();

    return response()->json(['message' => 'Deleted']);
  }

  // GET /api/staff/class-activities/{activity}/download
  // only owner can download (multi-tenant safe)
  public function download(Request $request, ClassActivity $activity)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    abort_unless((int)$activity->school_id === (int)$schoolId, 403);
    abort_unless((int)$activity->uploaded_by_user_id === (int)$user->id, 403);

    if (!$activity->file_path || !Storage::disk('public')->exists($activity->file_path)) {
      return response()->json(['message' => 'File not found'], 404);
    }

    return Storage::disk('public')->download(
      $activity->file_path,
      $activity->original_name ?: basename($activity->file_path)
    );
  }
}
