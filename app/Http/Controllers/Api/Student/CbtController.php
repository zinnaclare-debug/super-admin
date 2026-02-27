<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\CbtExam;
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
            if ($current) return (int)$current->id;
        }

        $fallback = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->orderBy('id')
            ->first();

        return $fallback ? (int)$fallback->id : null;
    }

    private function allowedTermSubjectIds(Request $request): array
    {
        $subjectsRes = $this->subjects($request)->getData(true);
        return collect($subjectsRes['data'] ?? [])
            ->pluck('term_subject_id')
            ->map(fn($v) => (int) $v)
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

        $currentTermId = $this->resolveCurrentTermId((int)$schoolId, (int)$session->id);
        if (!$currentTermId) return response()->json(['data' => []]);

        $student = Student::where('user_id', $user->id)->where('school_id', $schoolId)->first();
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
            ->map(function ($x) use ($now) {
                $startsAt = $x->starts_at ? Carbon::parse($x->starts_at) : null;
                $endsAt = $x->ends_at ? Carbon::parse($x->ends_at) : null;
                $x->is_open = $startsAt && $endsAt ? $now->between($startsAt, $endsAt) : false;
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

        $allowed = $this->allowedTermSubjectIds($request);
        abort_unless(in_array((int) $exam->term_subject_id, $allowed, true), 403);

        $now = Carbon::now();
        $startsAt = $exam->starts_at ? Carbon::parse($exam->starts_at) : null;
        $endsAt = $exam->ends_at ? Carbon::parse($exam->ends_at) : null;
        if (!$startsAt || !$endsAt || !$now->between($startsAt, $endsAt)) {
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

        return response()->json(['data' => $questions]);
    }
}
