<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\TermSubject;
use App\Models\VirtualClass;
use App\Services\Hms\HmsRoomService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VirtualClassesController extends Controller
{
  private const CLASS_TYPES = ['virtual', 'live'];
  private const PROVIDERS = ['zoom', 'google_meet', 'microsoft_teams', '100ms', 'livekit', 'jitsi', 'other', 'external'];
  private const STATUSES = ['scheduled', 'live', 'ended'];

  public function assignedSubjects(Request $request)
  {
    return $this->myAssignedSubjects($request);
  }

  private function hasTeacherAssignmentColumn(): bool
  {
    return Schema::hasColumn('term_subjects', 'teacher_user_id');
  }

  private function validateTermSubject(Request $request, int $termSubjectId): array
  {
    $user = $request->user();
    $schoolId = $user->school_id;

    if (!$this->hasTeacherAssignmentColumn()) {
      abort(response()->json([
        'message' => 'Teacher assignment schema is missing. Run database migrations and try again.'
      ], 500));
    }

    $session = AcademicSession::where('school_id', $schoolId)
      ->where('status', 'current')
      ->first();

    if (!$session) {
      abort(response()->json(['message' => 'No current session'], 422));
    }

    $currentTerm = Term::where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->where('is_current', true)
      ->first();

    if (!$currentTerm) {
      abort(response()->json(['message' => 'No current term'], 422));
    }

    $ts = TermSubject::where('id', $termSubjectId)
      ->where('school_id', $schoolId)
      ->where('teacher_user_id', $user->id)
      ->first();

    if (!$ts) {
      abort(response()->json(['message' => 'You are not assigned to this subject'], 403));
    }

    $term = Term::where('id', $ts->term_id)
      ->where('school_id', $schoolId)
      ->first();

    if (
      !$term ||
      (int)$term->academic_session_id !== (int)$session->id ||
      (int)$term->id !== (int)$currentTerm->id
    ) {
      abort(response()->json(['message' => 'Create allowed only for current session and current term'], 403));
    }

    return [$ts, $session, $currentTerm];
  }

  private function parseDateTime(?string $value): ?string
  {
    if (!$value) {
      return null;
    }

    return Carbon::parse($value)->format('Y-m-d H:i:s');
  }

  private function ensureNoOtherLiveClass(int $schoolId, int $termSubjectId, ?int $exceptId = null): void
  {
    $query = VirtualClass::query()
      ->where('school_id', $schoolId)
      ->where('term_subject_id', $termSubjectId)
      ->where('class_type', 'live')
      ->where('status', 'live');

    if ($exceptId) {
      $query->where('id', '!=', $exceptId);
    }

    if ($query->exists()) {
      abort(response()->json([
        'message' => 'There is already an active live class for this subject.'
      ], 422));
    }
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
      'class_type' => 'nullable|string|in:virtual,live',
      'status' => 'nullable|string|in:scheduled,live,ended',
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
      ->when(!empty($data['class_type']), function ($q) use ($data) {
        $q->where('virtual_classes.class_type', $data['class_type']);
      })
      ->when(!empty($data['status']), function ($q) use ($data) {
        $q->where('virtual_classes.status', $data['status']);
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
      'class_type' => 'nullable|string|in:virtual,live',
      'title' => 'required|string|max:150',
      'description' => 'nullable|string|max:1000',
      'provider' => 'nullable|string|in:zoom,google_meet,microsoft_teams,100ms,livekit,jitsi,other,external',
      'status' => 'nullable|string|in:scheduled,live,ended',
      'meeting_link' => 'nullable|url|max:1000',
      'starts_at' => 'nullable|date',
      'ends_at' => 'nullable|date|after:starts_at',
    ]);

    [$ts] = $this->validateTermSubject($request, (int)$data['term_subject_id']);

    $classType = $data['class_type'] ?? 'virtual';
    $provider = $data['provider'] ?? ($classType === 'virtual' ? 'external' : '100ms');
    $status = $data['status'] ?? ($classType === 'live' ? 'live' : 'live');
    $meetingLink = $data['meeting_link'] ?? null;

    if (!($classType === 'live' && $provider === '100ms') && !$meetingLink) {
      return response()->json(['message' => 'Meeting link is required for this class provider'], 422);
    }

    if ($classType === 'live' && $provider === '100ms' && !$meetingLink) {
      $meetingLink = rtrim((string) config('app.url', 'http://localhost'), '/') . '/virtual-class/live/' . Str::uuid();
    }

    if ($classType === 'live' && $status === 'live') {
      $this->ensureNoOtherLiveClass((int)$schoolId, (int)$ts->id);
    }

    $startsAt = $this->parseDateTime($data['starts_at'] ?? null);
    $endsAt = $this->parseDateTime($data['ends_at'] ?? null);
    $liveStartedAt = $classType === 'live' && $status === 'live'
      ? ($startsAt ?? now()->format('Y-m-d H:i:s'))
      : null;
    $liveEndedAt = $status === 'ended'
      ? now()->format('Y-m-d H:i:s')
      : null;

    $row = VirtualClass::create([
      'school_id' => $schoolId,
      'uploaded_by_user_id' => $user->id,
      'term_subject_id' => $ts->id,
      'class_type' => $classType,
      'title' => $data['title'],
      'description' => $data['description'] ?? null,
      'provider' => $provider,
      'status' => $status,
      'meeting_link' => $meetingLink,
      'starts_at' => $startsAt,
      'ends_at' => $endsAt,
      'live_started_at' => $liveStartedAt,
      'live_ended_at' => $liveEndedAt,
    ]);

    if ($classType === 'live' && $provider === '100ms') {
      $row = app(HmsRoomService::class)->ensureRoomProvisioned($row);
    }

    return response()->json([
      'message' => $classType === 'live' ? 'Live class created' : 'Virtual class created',
      'data' => $row,
    ], 201);
  }

  // POST /api/staff/virtual-classes/{virtualClass}/start
  public function start(Request $request, VirtualClass $virtualClass)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    abort_unless((int)$virtualClass->school_id === (int)$user->school_id, 403);
    abort_unless((int)$virtualClass->uploaded_by_user_id === (int)$user->id, 403);

    [$ts] = $this->validateTermSubject($request, (int)$virtualClass->term_subject_id);
    $this->ensureNoOtherLiveClass((int)$user->school_id, (int)$ts->id, (int)$virtualClass->id);

    if ($virtualClass->provider === '100ms') {
      $virtualClass = app(HmsRoomService::class)->ensureRoomProvisioned($virtualClass);
    }

    $virtualClass->update([
      'class_type' => $virtualClass->class_type ?: 'live',
      'status' => 'live',
      'live_started_at' => now(),
      'live_ended_at' => null,
    ]);

    return response()->json([
      'message' => 'Live class started',
      'data' => $virtualClass->fresh(),
    ]);
  }

  // GET /api/staff/virtual-classes/{virtualClass}/session
  public function session(Request $request, VirtualClass $virtualClass, HmsRoomService $hmsRoomService)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    abort_unless((int)$virtualClass->school_id === (int)$user->school_id, 403);
    abort_unless((int)$virtualClass->uploaded_by_user_id === (int)$user->id, 403);

    if ($virtualClass->class_type !== 'live' || $virtualClass->provider !== '100ms') {
      return response()->json(['message' => 'This class is not configured for 100ms live sessions'], 422);
    }

    if ($virtualClass->status === 'ended') {
      return response()->json(['message' => 'This live class has already ended'], 422);
    }

    return response()->json([
      'data' => $hmsRoomService->buildSessionPayload($virtualClass, $user, 'staff'),
    ]);
  }

  // POST /api/staff/virtual-classes/{virtualClass}/end
  public function end(Request $request, VirtualClass $virtualClass)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    abort_unless((int)$virtualClass->school_id === (int)$user->school_id, 403);
    abort_unless((int)$virtualClass->uploaded_by_user_id === (int)$user->id, 403);

    [$ts] = $this->validateTermSubject($request, (int)$virtualClass->term_subject_id);

    $virtualClass->update([
      'class_type' => $virtualClass->class_type ?: 'live',
      'status' => 'ended',
      'live_ended_at' => now(),
      'ends_at' => now(),
    ]);

    return response()->json([
      'message' => 'Class ended',
      'data' => $virtualClass->fresh(),
    ]);
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
