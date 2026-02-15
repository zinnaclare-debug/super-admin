<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\CbtExam;
use App\Models\CbtExamQuestion;
use App\Models\QuestionBankQuestion;
use App\Models\Term;
use App\Models\TermSubject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CbtController extends Controller
{
  private function hasTeacherAssignmentColumn(): bool
  {
    return Schema::hasColumn('term_subjects', 'teacher_user_id');
  }

  // GET /api/staff/cbt/subjects
  public function subjects(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    if (!$this->hasTeacherAssignmentColumn()) {
      return response()->json([
        'data' => [],
        'message' => 'Teacher assignment schema is missing. Run database migrations and try again.'
      ]);
    }

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
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
        'term_subjects.subject_id as subject_id',
        'subjects.name as subject_name',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.id as term_id',
        'terms.name as term_name',
      ]);

    return response()->json(['data' => $rows]);
  }

  // GET /api/staff/cbt/exams
  public function index(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    $items = CbtExam::query()
      ->join('term_subjects', 'term_subjects.id', '=', 'cbt_exams.term_subject_id')
      ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->where('cbt_exams.school_id', $schoolId)
      ->where('cbt_exams.teacher_user_id', $user->id)
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
      ->orderByDesc('cbt_exams.id')
      ->get([
        'cbt_exams.*',
        'subjects.name as subject_name',
        'classes.name as class_name',
        'terms.name as term_name',
      ]);

    return response()->json(['data' => $items]);
  }

  // POST /api/staff/cbt/exams
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
      'instructions' => 'nullable|string|max:5000',
      'starts_at' => 'required|date',
      'ends_at' => 'required|date|after:starts_at',
      'duration_minutes' => 'required|integer|min:1|max:300',
      'status' => 'nullable|string|in:draft,closed',
      'security_policy' => 'nullable|array',
      'security_policy.fullscreen_required' => 'nullable|boolean',
      'security_policy.block_copy_paste' => 'nullable|boolean',
      'security_policy.block_tab_switch' => 'nullable|boolean',
      'security_policy.no_face_timeout_seconds' => 'nullable|integer|min:10|max:300',
      'security_policy.max_warnings' => 'nullable|integer|min:1|max:20',
      'security_policy.auto_submit_on_violation' => 'nullable|boolean',
      'security_policy.logout_on_violation' => 'nullable|boolean',
      'security_policy.ai_proctoring_enabled' => 'nullable|boolean',
    ]);

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
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
    if (!$ts) return response()->json(['message' => 'You are not assigned to this subject'], 403);

    $term = Term::where('id', $ts->term_id)->where('school_id', $schoolId)->first();
    if (
      !$term ||
      (int)$term->academic_session_id !== (int)$session->id ||
      (int)$term->id !== (int)$currentTerm->id
    ) {
      return response()->json(['message' => 'CBT can only be created for current session and current term'], 403);
    }

    $exam = CbtExam::create([
      'school_id' => $schoolId,
      'teacher_user_id' => $user->id,
      'term_subject_id' => $ts->id,
      'title' => $data['title'],
      'instructions' => $data['instructions'] ?? null,
      'starts_at' => Carbon::parse($data['starts_at'])->format('Y-m-d H:i:s'),
      'ends_at' => Carbon::parse($data['ends_at'])->format('Y-m-d H:i:s'),
      'duration_minutes' => $data['duration_minutes'],
      'status' => $data['status'] ?? 'draft',
      'security_policy' => $data['security_policy'] ?? null,
    ]);

    return response()->json(['message' => 'CBT exam created', 'data' => $exam], 201);
  }

  // GET /api/staff/cbt/exams/{exam}/questions
  public function examQuestions(Request $request, CbtExam $exam)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    abort_unless((int)$exam->school_id === (int)$schoolId, 403);
    abort_unless((int)$exam->teacher_user_id === (int)$user->id, 403);

    $items = CbtExamQuestion::where('school_id', $schoolId)
      ->where('cbt_exam_id', $exam->id)
      ->orderBy('position')
      ->orderBy('id')
      ->get();

    return response()->json(['data' => $items]);
  }

  // POST /api/staff/cbt/exams/{exam}/export-question-bank
  public function exportFromQuestionBank(Request $request, CbtExam $exam)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    abort_unless((int)$exam->school_id === (int)$schoolId, 403);
    abort_unless((int)$exam->teacher_user_id === (int)$user->id, 403);

    $data = $request->validate([
      'question_ids' => 'required|array|min:1',
      'question_ids.*' => 'required|integer',
    ]);

    $questions = QuestionBankQuestion::where('school_id', $schoolId)
      ->where('teacher_user_id', $user->id)
      ->whereIn('id', $data['question_ids'])
      ->get();

    if ($questions->isEmpty()) {
      return response()->json(['message' => 'No valid questions selected'], 422);
    }

    DB::transaction(function () use ($schoolId, $exam, $questions) {
      $startPos = (int)CbtExamQuestion::where('cbt_exam_id', $exam->id)->max('position');

      foreach ($questions as $idx => $q) {
        CbtExamQuestion::create([
          'school_id' => $schoolId,
          'cbt_exam_id' => $exam->id,
          'question_bank_question_id' => $q->id,
          'question_text' => $q->question_text,
          'option_a' => $q->option_a,
          'option_b' => $q->option_b,
          'option_c' => $q->option_c,
          'option_d' => $q->option_d,
          'correct_option' => $q->correct_option,
          'explanation' => $q->explanation,
          'media_path' => $q->media_path,
          'media_type' => $q->media_type,
          'position' => $startPos + $idx + 1,
        ]);
      }
    });

    return response()->json(['message' => 'Questions exported to CBT']);
  }

  public function destroy(Request $request, CbtExam $exam)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);
    $schoolId = $user->school_id;

    abort_unless((int)$exam->school_id === (int)$schoolId, 403);
    abort_unless((int)$exam->teacher_user_id === (int)$user->id, 403);

    $exam->delete();

    return response()->json(['message' => 'Deleted']);
  }
}
