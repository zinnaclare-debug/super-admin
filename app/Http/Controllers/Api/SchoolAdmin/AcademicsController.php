<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\CbtExam;
use App\Models\CbtExamQuestion;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\Subject;
use App\Models\TermSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicsController extends Controller
{
    /**
     * GET /api/school-admin/academics
     * Shows ONLY activated levels (session->levels) and their classes
     */
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json([
                'data' => null,
                'message' => 'No current academic session'
            ], 200);
        }

        $rawLevels = $session->levels ?? [];

        // Normalize levels: accept either ["nursery","primary"] or [{"level":"nursery"}, ...]
        $activeLevels = collect($rawLevels)
            ->map(function ($item) {
                if (is_array($item)) {
                    return isset($item['level']) ? strtolower($item['level']) : null;
                }
                return is_string($item) ? strtolower($item) : null;
            })
            ->filter()
            ->values()
            ->toArray();

        $allowed = ['nursery', 'primary', 'secondary'];
        $activeLevels = array_values(array_filter(
            $activeLevels,
            fn ($l) => in_array($l, $allowed, true)
        ));

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->whereIn('level', $activeLevels)
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'session' => $session,
                'active_levels' => $activeLevels,
                'classes' => $classes,
            ]
        ]);
    }

    /**
     * GET /api/school-admin/classes/{class}/terms/{term}/subjects
     * Return subjects attached to this class + term.
     */
    public function termSubjects(Request $request, SchoolClass $class, Term $term)
    {
        $schoolId = $request->user()->school_id;

        abort_unless($class->school_id === $schoolId, 403);
        abort_unless($term->school_id === $schoolId, 403);
        abort_unless((int)$term->academic_session_id === (int)$class->academic_session_id, 400);

        // If term_subjects has school_id column (your new migration), we use it.
        $hasSchoolId = Schema::hasColumn('term_subjects', 'school_id');

        $query = Subject::query()
            ->select('subjects.id', 'subjects.name', 'subjects.code')
            ->join('term_subjects', 'term_subjects.subject_id', '=', 'subjects.id')
            ->where('term_subjects.class_id', $class->id)
            ->where('term_subjects.term_id', $term->id)
            ->where('subjects.school_id', $schoolId)
            ->orderBy('subjects.name');

        if ($hasSchoolId) {
            $query->where('term_subjects.school_id', $schoolId);
        }

        $items = $query->get();

        return response()->json(['data' => $items]);
    }

// GET /api/school-admin/classes/{class}/terms/{term}/courses
public function termCourses(Request $request, SchoolClass $class, Term $term)
{
    $schoolId = $request->user()->school_id;

    abort_unless($class->school_id === $schoolId, 403);
    abort_unless($term->school_id === $schoolId, 403);
    abort_unless($term->academic_session_id === $class->academic_session_id, 400);

    // term_subjects = the assignment table for courses per class+term
    $rows = TermSubject::query()
        ->where('term_subjects.school_id', $schoolId)
        ->where('term_subjects.class_id', $class->id)
        ->where('term_subjects.term_id', $term->id)
        ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
        ->leftJoin('users as teacher', 'teacher.id', '=', 'term_subjects.teacher_user_id')
        ->orderBy('subjects.name')
        ->get([
            'term_subjects.id as term_subject_id',
            'subjects.id as subject_id',
            'subjects.name as name',
            'subjects.code as code',
            'term_subjects.teacher_user_id',
            'teacher.name as teacher_name',
        ]);

    return response()->json([
        'data' => $rows
    ]);
}




    /**
     * POST /api/school-admin/classes/{class}/subjects
     * Bulk create subjects and assign them to selected terms
     *
     * Payload:
     * {
     *   "subjects": [{"name":"Mathematics","code":"MTH"}, ...],
     *   "term_ids": [1,2,3]
     * }
     */
    public function createSubjects(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $payload = $request->validate([
            'subjects' => 'required|array|min:1',
            'subjects.*.name' => 'required|string|max:100',
            'subjects.*.code' => 'nullable|string|max:20',
            'term_ids' => 'required|array|min:1',
            'term_ids.*' => 'integer',
        ]);

        // validate term IDs belong to this school + same session as class
        $termIds = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->whereIn('id', $payload['term_ids'])
            ->pluck('id')
            ->toArray();

        if (count($termIds) !== count($payload['term_ids'])) {
            return response()->json(['message' => 'Invalid terms selected'], 422);
        }

        $hasSchoolId = Schema::hasColumn('term_subjects', 'school_id');

        return DB::transaction(function () use ($payload, $schoolId, $class, $termIds, $hasSchoolId) {

            foreach ($payload['subjects'] as $s) {
                $name = trim($s['name']);

                // Subject is per-school
                $subject = Subject::updateOrCreate(
                    ['school_id' => $schoolId, 'name' => $name],
                    ['code' => $s['code'] ?? null]
                );

                foreach ($termIds as $tid) {
                    $where = [
                        'class_id' => $class->id,
                        'term_id' => $tid,
                        'subject_id' => $subject->id,
                    ];

                    if ($hasSchoolId) {
                        $where['school_id'] = $schoolId;
                    }

                    TermSubject::updateOrCreate($where, []);
                }
            }

            return response()->json([
                'message' => 'Subjects created and assigned to terms'
            ], 201);
        });
    }

    /**
     * PATCH /api/school-admin/classes/{class}/terms/{term}/subjects/{subject}/assign-teacher
     * Assign a teacher to a subject for a specific class+term
     */
public function assignTeacherToSubject(Request $request, SchoolClass $class, Term $term, Subject $subject)
{
    $schoolId = $request->user()->school_id;

    abort_unless($class->school_id === $schoolId, 403);
    abort_unless($term->school_id === $schoolId, 403);
    abort_unless($subject->school_id === $schoolId, 403);
    abort_unless($term->academic_session_id === $class->academic_session_id, 400);

    $data = $request->validate([
        'teacher_user_id' => 'required|integer|exists:users,id',
    ]);

    // (Optional but recommended) ensure teacher belongs to same school and is staff/teacher
    $teacher = \App\Models\User::where('id', $data['teacher_user_id'])
        ->where('school_id', $schoolId)
        ->firstOrFail();

    $sessionTermIds = Term::where('school_id', $schoolId)
        ->where('academic_session_id', $class->academic_session_id)
        ->pluck('id')
        ->all();

    TermSubject::where('school_id', $schoolId)
        ->where('class_id', $class->id)
        ->whereIn('term_id', $sessionTermIds)
        ->where('subject_id', $subject->id)
        ->update(['teacher_user_id' => $teacher->id]);

    return response()->json(['message' => 'Teacher assigned for all terms in this session']);
}

public function unassignTeacherFromSubject(Request $request, SchoolClass $class, Term $term, Subject $subject)
{
    $schoolId = $request->user()->school_id;

    abort_unless($class->school_id === $schoolId, 403);
    abort_unless($term->school_id === $schoolId, 403);
    abort_unless($subject->school_id === $schoolId, 403);
    abort_unless($term->academic_session_id === $class->academic_session_id, 400);

    $sessionTermIds = Term::where('school_id', $schoolId)
        ->where('academic_session_id', $class->academic_session_id)
        ->pluck('id')
        ->all();

    TermSubject::where('school_id', $schoolId)
        ->where('class_id', $class->id)
        ->whereIn('term_id', $sessionTermIds)
        ->where('subject_id', $subject->id)
        ->update(['teacher_user_id' => null]);

    return response()->json(['message' => 'Teacher unassigned for all terms in this session']);
}

    /**
     * GET /api/school-admin/classes/{class}/terms/{term}/subjects/{subject}/cbt-exams
     * List CBT exams created by staff for this subject assignment.
     */
    public function cbtExamsForSubject(Request $request, SchoolClass $class, Term $term, Subject $subject)
    {
        $schoolId = $request->user()->school_id;

        abort_unless((int) $class->school_id === (int) $schoolId, 403);
        abort_unless((int) $term->school_id === (int) $schoolId, 403);
        abort_unless((int) $subject->school_id === (int) $schoolId, 403);
        abort_unless((int) $term->academic_session_id === (int) $class->academic_session_id, 400);

        if (strtolower((string) $class->level) !== 'secondary') {
            return response()->json(['message' => 'CBT publish is only available for secondary classes'], 422);
        }

        $termSubject = TermSubject::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->where('subject_id', $subject->id)
            ->first();

        if (!$termSubject) {
            return response()->json(['data' => []]);
        }

        $rows = CbtExam::query()
            ->where('cbt_exams.school_id', $schoolId)
            ->where('cbt_exams.term_subject_id', $termSubject->id)
            ->leftJoin('users as staff', 'staff.id', '=', 'cbt_exams.teacher_user_id')
            ->orderByDesc('cbt_exams.id')
            ->get([
                'cbt_exams.id',
                'cbt_exams.title',
                'cbt_exams.instructions',
                'cbt_exams.starts_at',
                'cbt_exams.ends_at',
                'cbt_exams.duration_minutes',
                'cbt_exams.status',
                'cbt_exams.teacher_user_id',
                'staff.name as teacher_name',
            ])
            ->map(function ($exam) use ($schoolId) {
                $exam->questions_count = CbtExamQuestion::where('school_id', $schoolId)
                    ->where('cbt_exam_id', $exam->id)
                    ->count();
                return $exam;
            });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'term_subject_id' => $termSubject->id,
                'class_name' => $class->name,
                'class_level' => $class->level,
                'term_name' => $term->name,
                'subject_name' => $subject->name,
            ],
        ]);
    }

    /**
     * PATCH /api/school-admin/cbt/exams/{exam}/publish
     * Final school-admin publish control for student visibility.
     */
    public function publishCbtExam(Request $request, CbtExam $exam)
    {
        $schoolId = $request->user()->school_id;
        abort_unless((int) $exam->school_id === (int) $schoolId, 403);

        $termSubject = TermSubject::query()
            ->where('school_id', $schoolId)
            ->where('id', $exam->term_subject_id)
            ->first();

        if (!$termSubject) {
            return response()->json(['message' => 'Exam subject mapping not found'], 404);
        }

        $class = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('id', $termSubject->class_id)
            ->first();

        if (!$class || strtolower((string) $class->level) !== 'secondary') {
            return response()->json(['message' => 'Only secondary class CBT can be published from this action'], 422);
        }

        $questionCount = CbtExamQuestion::where('school_id', $schoolId)
            ->where('cbt_exam_id', $exam->id)
            ->count();
        if ($questionCount < 1) {
            return response()->json(['message' => 'Cannot publish exam without questions'], 422);
        }

        $exam->status = 'published';
        $exam->save();

        return response()->json([
            'message' => 'CBT exam published',
            'data' => [
                'id' => $exam->id,
                'status' => $exam->status,
            ],
        ]);
    }


}
