<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\TermSubject;
use App\Models\VirtualClass;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VirtualClassesController extends Controller
{
  public function assignedSubjects(Request $request)
  {
    return $this->myAssignedSubjects($request);
  }

  private function hasTeacherAssignmentColumn(): bool
  {
    return Schema::hasColumn('term_subjects', 'teacher_user_id');
  }

  // GET /api/staff/virtual-classes/subjects
  // Only subjects this teacher is assigned to (current session)
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
        'subjects.id as subject_id',
        'subjects.name as subject_name',
        'subjects.code as subject_code',
        'classes.id as class_id',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.id as term_id',
        'terms.name as term_name',
      ]);

    return response()->json(['data' => $rows]);
  }

  // GET /api/staff/virtual-classes?term_subject_id=123&subject_id=4
  public function index(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;
    $data = $request->validate([
      'term_subject_id' => 'nullable|integer',
      'subject_id' => 'nullable|integer',
    ]);

    $items = VirtualClass::query()
      ->join('term_subjects', 'term_subjects.id', '=', 'virtual_classes.term_subject_id')
      ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->where('virtual_classes.school_id', $schoolId)
      ->where('virtual_classes.uploaded_by_user_id', $user->id)
      ->where(function ($q) use ($schoolId) {
        $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
        if (!$session) {
          $q->whereRaw('1 = 0');
          return;
        }
        $currentTerm = Term::where('school_id', $schoolId)
          ->where('academic_session_id', $session->id)
          ->where('is_current', true)
          ->first();
        if (!$currentTerm) {
          $q->whereRaw('1 = 0');
          return;
        }
        $q->where('term_subjects.term_id', $currentTerm->id);
      })
      ->when(!empty($data['term_subject_id']), function ($q) use ($data) {
        $q->where('virtual_classes.term_subject_id', (int)$data['term_subject_id']);
      })
      ->when(!empty($data['subject_id']), function ($q) use ($data) {
        $q->where('term_subjects.subject_id', (int)$data['subject_id']);
      })
      ->orderByDesc('virtual_classes.id')
      ->get([
        'virtual_classes.*',
        'subjects.id as subject_id',
        'subjects.name as subject_name',
        'subjects.code as subject_code',
        'classes.id as class_id',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.id as term_id',
        'terms.name as term_name',
      ]);

    return response()->json(['data' => $items]);
  }

  // POST /api/staff/virtual-classes
  // create only for term_subject assigned to this teacher in current session
  public function store(Request $request)
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
      'meeting_link' => 'required|url|max:1000',
      'starts_at' => 'nullable|date',
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

    $ts = TermSubject::where('id', $data['term_subject_id'])
      ->where('school_id', $schoolId)
      ->where('teacher_user_id', $user->id)
      ->first();

    if (!$ts) {
      return response()->json(['message' => 'You are not assigned to this subject'], 403);
    }

    $term = Term::where('id', $ts->term_id)
      ->where('school_id', $schoolId)
      ->first();

    if (
      !$term ||
      (int)$term->academic_session_id !== (int)$session->id ||
      (int)$term->id !== (int)$currentTerm->id
    ) {
      return response()->json(['message' => 'Create allowed only for current session and current term'], 403);
    }

    if (!str_contains(strtolower($data['meeting_link']), 'zoom.us')) {
      return response()->json(['message' => 'Please provide a valid Zoom link'], 422);
    }

    $startsAt = null;
    if (!empty($data['starts_at'])) {
      $startsAt = Carbon::parse($data['starts_at'])->format('Y-m-d H:i:s');
    }

    $row = VirtualClass::create([
      'school_id' => $schoolId,
      'uploaded_by_user_id' => $user->id,
      'term_subject_id' => $ts->id,
      'title' => $data['title'],
      'description' => $data['description'] ?? null,
      'meeting_link' => $data['meeting_link'],
      'starts_at' => $startsAt,
    ]);

    return response()->json([
      'message' => 'Virtual class created',
      'data' => $row,
    ], 201);
  }

  // DELETE /api/staff/virtual-classes/{virtualClass}
  public function destroy(Request $request, VirtualClass $virtualClass)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    abort_unless((int)$virtualClass->school_id === (int)$schoolId, 403);
    abort_unless((int)$virtualClass->uploaded_by_user_id === (int)$user->id, 403);

    $virtualClass->delete();

    return response()->json(['message' => 'Deleted']);
  }
}
