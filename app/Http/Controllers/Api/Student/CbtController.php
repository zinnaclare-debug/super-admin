<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\CbtExam;
use App\Models\CbtExamAttempt;
use App\Models\CbtExamQuestion;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermSubject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CbtController extends Controller
{
    private function endedAttemptStatuses(): array
    {
        return ['submitted', 'exited', 'time_up', 'disqualified'];
    }

    private function isEndedAttemptStatus(?string $status): bool
    {
        return in_array((string) $status, $this->endedAttemptStatuses(), true);
    }

    private function examDurationMinutes(CbtExam $exam): int
    {
        $minutes = (int) ($exam->duration_minutes ?? $exam->duration ?? 60);
        return max(1, $minutes);
    }

    private function attemptWindow(CbtExam $exam, ?Carbon $attemptStartedAt = null): array
    {
        $startsAt = $exam->starts_at ? Carbon::parse($exam->starts_at) : null;
        $hardEndsAt = $exam->ends_at ? Carbon::parse($exam->ends_at) : null;
        if (!$startsAt || !$hardEndsAt) {
            return [null, null];
        }

        if (!$attemptStartedAt) {
            return [$startsAt, $hardEndsAt];
        }

        $durationEndsAt = $attemptStartedAt->copy()->addMinutes($this->examDurationMinutes($exam));
        $effectiveEndsAt = $durationEndsAt->lessThan($hardEndsAt) ? $durationEndsAt : $hardEndsAt;

        return [$startsAt, $effectiveEndsAt];
    }

    private function resolveStudentRecord(int $schoolId, int $userId): ?Student
    {
        return Student::where('user_id', $userId)->where('school_id', $schoolId)->first();
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

    private function resolveCurrentTermId(int $schoolId, int $sessionId): ?int
    {
        if (Schema::hasColumn('terms', 'is_current')) {
            $current = Term::where('school_id', $schoolId)
                ->where('academic_session_id', $sessionId)
                ->where('is_current', true)
                ->first();
            if ($current) return (int) $current->id;
        }

        $fallback = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->orderBy('id')
            ->first();

        return $fallback ? (int) $fallback->id : null;
    }

    private function allowedTermSubjectIds(Request $request): array
    {
        $subjectsRes = $this->subjects($request)->getData(true);
        return collect($subjectsRes['data'] ?? [])
            ->pluck('term_subject_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    // GET /api/student/cbt/subjects
    public function subjects(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);
        $schoolId = $user->school_id;

        $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
        if (!$session) return response()->json(['data' => []]);

        $currentTermId = $this->resolveCurrentTermId((int) $schoolId, (int) $session->id);
        if (!$currentTermId) return response()->json(['data' => []]);

        $student = $this->resolveStudentRecord((int) $schoolId, (int) $user->id);
        if (!$student) return response()->json(['data' => []]);

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id, $currentTermId);
        if (empty($classIds)) return response()->json(['data' => []]);

        $rows = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.term_id', $currentTermId)
            ->whereIn('term_subjects.class_id', $classIds)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
            ->when(Schema::hasTable('student_subject_exclusions'), function ($query) use ($schoolId, $session, $student) {
                $query->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $session, $student) {
                    $join->on('student_subject_exclusions.class_id', '=', 'term_subjects.class_id')
                        ->on('student_subject_exclusions.subject_id', '=', 'term_subjects.subject_id')
                        ->where('student_subject_exclusions.school_id', '=', $schoolId)
                        ->where('student_subject_exclusions.academic_session_id', '=', (int) $session->id)
                        ->where('student_subject_exclusions.student_id', '=', (int) $student->id);
                })->whereNull('student_subject_exclusions.id');
            })
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

    // GET /api/student/cbt/exams
    public function exams(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);
        $schoolId = $user->school_id;

        $allowed = $this->allowedTermSubjectIds($request);
        if (empty($allowed)) return response()->json(['data' => []]);

        $student = $this->resolveStudentRecord((int) $schoolId, (int) $user->id);
        if (!$student) return response()->json(['data' => []]);

        $now = Carbon::now();

        $items = CbtExam::query()
            ->join('term_subjects', 'term_subjects.id', '=', 'cbt_exams.term_subject_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
            ->where('cbt_exams.school_id', $schoolId)
            ->where('cbt_exams.status', 'published')
            ->whereIn('cbt_exams.term_subject_id', $allowed)
            ->orderBy('cbt_exams.starts_at')
            ->get([
                'cbt_exams.id',
                'cbt_exams.term_subject_id',
                'cbt_exams.title',
                'cbt_exams.instructions',
                'cbt_exams.starts_at',
                'cbt_exams.ends_at',
                'cbt_exams.duration_minutes',
                'cbt_exams.security_policy',
                'subjects.name as subject_name',
                'classes.name as class_name',
                'terms.name as term_name',
            ])
            ->values();

        $attemptsByExam = CbtExamAttempt::query()
            ->where('school_id', $schoolId)
            ->where('student_id', (int) $student->id)
            ->whereIn('cbt_exam_id', $items->pluck('id')->map(fn ($v) => (int) $v)->all())
            ->get()
            ->keyBy('cbt_exam_id');

        $items = $items->map(function ($x) use ($now, $attemptsByExam) {
            $attempt = $attemptsByExam->get((int) $x->id);
            [$startsAt, $effectiveEndsAt] = $this->attemptWindow(
                $x,
                $attempt?->started_at ? Carbon::parse($attempt->started_at) : null
            );

            $windowOpen = $startsAt && $effectiveEndsAt ? $now->between($startsAt, $effectiveEndsAt) : false;
            $hasTaken = $attempt ? $this->isEndedAttemptStatus($attempt->status) : false;
            $x->is_open = $windowOpen && !$hasTaken;
            $x->can_start = $x->is_open;
            $x->has_taken = $hasTaken;
            $x->attempt_status = $attempt?->status;
            $x->attempt_ended_at = $attempt?->ended_at;
            return $x;
        });

        return response()->json(['data' => $items]);
    }

    // GET /api/student/cbt/exams/{exam}/questions
    public function questions(Request $request, CbtExam $exam)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);
        $schoolId = $user->school_id;

        abort_unless((int) $exam->school_id === (int) $schoolId, 403);
        abort_unless($exam->status === 'published', 403);

        $student = $this->resolveStudentRecord((int) $schoolId, (int) $user->id);
        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 403);
        }

        $allowed = $this->allowedTermSubjectIds($request);
        abort_unless(in_array((int) $exam->term_subject_id, $allowed, true), 403);

        $attempt = CbtExamAttempt::where('school_id', $schoolId)
            ->where('cbt_exam_id', (int) $exam->id)
            ->where('student_id', (int) $student->id)
            ->first();

        if ($attempt && $this->isEndedAttemptStatus($attempt->status)) {
            return response()->json([
                'message' => 'This CBT attempt has ended and cannot be reopened.',
            ], 403);
        }

        if (!$attempt) {
            $attempt = CbtExamAttempt::create([
                'school_id' => (int) $schoolId,
                'cbt_exam_id' => (int) $exam->id,
                'student_id' => (int) $student->id,
                'user_id' => (int) $user->id,
                'status' => 'in_progress',
                'started_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        $now = Carbon::now();
        [$startsAt, $effectiveEndsAt] = $this->attemptWindow(
            $exam,
            $attempt->started_at ? Carbon::parse($attempt->started_at) : $now
        );
        if (!$startsAt || !$effectiveEndsAt || !$now->between($startsAt, $effectiveEndsAt)) {
            if (
                !$this->isEndedAttemptStatus($attempt->status) &&
                $effectiveEndsAt &&
                $now->greaterThan($effectiveEndsAt)
            ) {
                $attempt->status = 'time_up';
                $attempt->submit_mode = 'auto';
                $attempt->ended_at = Carbon::now()->toDateTimeString();
                $attempt->save();
            }
            return response()->json([
                'message' => 'CBT questions are available only during the exam time window',
            ], 403);
        }

        $questions = CbtExamQuestion::query()
            ->where('school_id', $schoolId)
            ->where('cbt_exam_id', $exam->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get([
                'id',
                'cbt_exam_id',
                'question_text',
                'option_a',
                'option_b',
                'option_c',
                'option_d',
                'media_path',
                'media_type',
                'position',
            ]);

        return response()->json([
            'data' => $questions,
            'attempt' => [
                'id' => (int) $attempt->id,
                'status' => (string) $attempt->status,
                'started_at' => $attempt->started_at,
                'allowed_end_at' => $effectiveEndsAt ? $effectiveEndsAt->toDateTimeString() : null,
            ],
        ]);
    }

    // POST /api/student/cbt/exams/{exam}/submit
    public function submit(Request $request, CbtExam $exam)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);
        $schoolId = $user->school_id;

        abort_unless((int) $exam->school_id === (int) $schoolId, 403);
        abort_unless($exam->status === 'published', 403);

        $student = $this->resolveStudentRecord((int) $schoolId, (int) $user->id);
        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 403);
        }

        $allowed = $this->allowedTermSubjectIds($request);
        abort_unless(in_array((int) $exam->term_subject_id, $allowed, true), 403);

        $data = $request->validate([
            'answers' => 'nullable|array',
            'answers.*' => 'nullable|string|in:A,B,C,D,a,b,c,d',
            'submit_mode' => 'nullable|string|in:manual,auto,exit',
            'violation_reason' => 'nullable|string|max:80',
            'security_warnings' => 'nullable|integer|min:0|max:999',
            'head_movement_warnings' => 'nullable|integer|min:0|max:999',
        ]);

        $attempt = CbtExamAttempt::where('school_id', $schoolId)
            ->where('cbt_exam_id', (int) $exam->id)
            ->where('student_id', (int) $student->id)
            ->first();

        if (!$attempt) {
            $attempt = CbtExamAttempt::create([
                'school_id' => (int) $schoolId,
                'cbt_exam_id' => (int) $exam->id,
                'student_id' => (int) $student->id,
                'user_id' => (int) $user->id,
                'status' => 'in_progress',
                'started_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        if ($this->isEndedAttemptStatus($attempt->status)) {
            return response()->json([
                'message' => 'This CBT attempt has already ended and cannot be submitted again.',
            ], 409);
        }

        $now = Carbon::now();
        [$startsAt, $effectiveEndsAt] = $this->attemptWindow(
            $exam,
            $attempt->started_at ? Carbon::parse($attempt->started_at) : $now
        );
        if (!$startsAt || !$effectiveEndsAt || $now->lessThan($startsAt)) {
            return response()->json([
                'message' => 'CBT submit is allowed only during the exam time window',
            ], 403);
        }

        $questions = CbtExamQuestion::query()
            ->where('school_id', $schoolId)
            ->where('cbt_exam_id', $exam->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'correct_option']);

        $answers = collect($data['answers'] ?? [])
            ->mapWithKeys(fn ($v, $k) => [(int) $k => strtoupper((string) $v)]);

        $total = $questions->count();
        $attempted = 0;
        $correct = 0;

        foreach ($questions as $q) {
            $selected = (string) ($answers->get((int) $q->id, ''));
            if (!in_array($selected, ['A', 'B', 'C', 'D'], true)) {
                continue;
            }
            $attempted++;
            if (strtoupper((string) $q->correct_option) === $selected) {
                $correct++;
            }
        }

        $scorePercent = $total > 0 ? round(($correct / $total) * 100, 2) : 0.0;

        $requestedMode = (string) ($data['submit_mode'] ?? 'manual');
        $timedOut = $effectiveEndsAt ? $now->greaterThan($effectiveEndsAt) : false;
        $finalMode = $timedOut ? 'auto' : $requestedMode;
        $finalStatus = 'submitted';
        if ($timedOut) {
            $finalStatus = 'time_up';
        } elseif ($requestedMode === 'exit') {
            $finalStatus = 'exited';
        } elseif ($requestedMode === 'auto' && !empty($data['violation_reason'])) {
            $finalStatus = 'disqualified';
        }

        $attempt->fill([
            'status' => $finalStatus,
            'submit_mode' => $finalMode,
            'answers' => $answers->toArray(),
            'total_questions' => $total,
            'attempted' => $attempted,
            'correct' => $correct,
            'wrong' => max(0, $attempted - $correct),
            'unanswered' => max(0, $total - $attempted),
            'score_percent' => $scorePercent,
            'security_warnings' => (int) ($data['security_warnings'] ?? 0),
            'head_movement_warnings' => (int) ($data['head_movement_warnings'] ?? 0),
            'ended_at' => Carbon::now()->toDateTimeString(),
        ]);
        $attempt->save();

        return response()->json([
            'message' => 'CBT submitted successfully',
            'data' => [
                'cbt_exam_id' => (int) $exam->id,
                'status' => $finalStatus,
                'submit_mode' => $finalMode,
                'total_questions' => $total,
                'attempted' => $attempted,
                'correct' => $correct,
                'wrong' => max(0, $attempted - $correct),
                'unanswered' => max(0, $total - $attempted),
                'score_percent' => $scorePercent,
                'submitted_at' => Carbon::now()->toDateTimeString(),
            ],
        ]);
    }
}
