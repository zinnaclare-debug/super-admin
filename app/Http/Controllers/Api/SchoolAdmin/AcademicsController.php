<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\CbtExam;
use App\Models\CbtExamQuestion;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubjectExclusion;
use App\Models\Term;
use App\Models\Subject;
use App\Models\TermSubject;
use Illuminate\Database\QueryException;
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

        $activeLevels = collect((array) ($session->levels ?? []))
            ->map(function ($item) {
                if (is_array($item)) {
                    return isset($item['level']) ? strtolower($item['level']) : null;
                }
                return is_string($item) ? strtolower($item) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($activeLevels)) {
            $activeLevels = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->pluck('level')
                ->map(fn ($level) => strtolower(trim((string) $level)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $classesQuery = SchoolClass::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('level')
            ->orderBy('id');

        if (!empty($activeLevels)) {
            $classesQuery->whereIn('level', $activeLevels);
        }

        $classes = $classesQuery->get();

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
            // accepted for backward compatibility; ignored in favor of full-session assignment
            'term_ids' => 'sometimes|array|min:1',
            'term_ids.*' => 'integer',
        ]);

        // Always apply new subjects across all terms in this class session.
        $termIds = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->pluck('id')
            ->toArray();

        if (empty($termIds)) {
            return response()->json(['message' => 'No terms found for this class session'], 422);
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
                'message' => 'Subjects created and assigned for the whole session'
            ], 201);
        });
    }

    /**
     * PATCH /api/school-admin/subjects/{subject}
     * Update subject name/code for this school.
     */
    public function updateSubject(Request $request, Subject $subject)
    {
        $schoolId = $request->user()->school_id;
        abort_unless((int) $subject->school_id === (int) $schoolId, 403);

        $payload = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
        ]);

        $name = trim((string) $payload['name']);
        if ($name === '') {
            return response()->json(['message' => 'Subject name is required'], 422);
        }

        $duplicate = Subject::where('school_id', $schoolId)
            ->where('id', '!=', $subject->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'A subject with this name already exists'], 422);
        }

        $subject->name = $name;
        $subject->code = filled($payload['code'] ?? null) ? trim((string) $payload['code']) : null;

        try {
            $subject->save();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Subject code is already used. Use another code or leave it empty.',
            ], 422);
        }

        return response()->json([
            'message' => 'Subject updated successfully',
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
            ],
        ]);
    }

    /**
     * DELETE /api/school-admin/classes/{class}/subjects/{subject}
     * Remove subject mapping for the whole class session (all terms in that session).
     * If subject is no longer mapped anywhere in this school, delete the subject row too.
     */
    public function deleteSubjectFromClassSession(Request $request, SchoolClass $class, Subject $subject)
    {
        $schoolId = (int) $request->user()->school_id;

        abort_unless((int) $class->school_id === $schoolId, 403);
        abort_unless((int) $subject->school_id === $schoolId, 403);

        return DB::transaction(function () use ($schoolId, $class, $subject) {
            $sessionTermIds = Term::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $class->academic_session_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($sessionTermIds)) {
                return response()->json([
                    'message' => 'No terms found for this class session.',
                ], 422);
            }

            $termSubjectQuery = TermSubject::query()
                ->where('class_id', (int) $class->id)
                ->where('subject_id', (int) $subject->id)
                ->whereIn('term_id', $sessionTermIds);

            if (Schema::hasColumn('term_subjects', 'school_id')) {
                $termSubjectQuery->where('school_id', $schoolId);
            }

            $removedCount = $termSubjectQuery->count();
            if ($removedCount === 0) {
                return response()->json([
                    'message' => 'Subject is not mapped to this class session.',
                ], 404);
            }

            // Cascades to dependent rows (results, CBT, materials, etc.) via FK cascade.
            $termSubjectQuery->delete();

            $subjectStillUsedQuery = TermSubject::query()
                ->where('subject_id', (int) $subject->id);

            if (Schema::hasColumn('term_subjects', 'school_id')) {
                $subjectStillUsedQuery->where('school_id', $schoolId);
            } else {
                $subjectStillUsedQuery->whereIn('class_id', function ($q) use ($schoolId) {
                    $q->from('classes')->select('id')->where('school_id', $schoolId);
                });
            }

            if (! $subjectStillUsedQuery->exists()) {
                $subject->delete();
            }

            return response()->json([
                'message' => 'Subject deleted from class session successfully.',
                'meta' => [
                    'removed_term_subjects' => (int) $removedCount,
                ],
            ]);
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
     * GET /api/school-admin/classes/{class}/terms/{term}/subjects/{subject}/students
     * List students in class+term and whether they currently offer this subject.
     */
    public function subjectStudents(Request $request, SchoolClass $class, Term $term, Subject $subject)
    {
        $schoolId = (int) $request->user()->school_id;

        abort_unless((int) $class->school_id === $schoolId, 403);
        abort_unless((int) $term->school_id === $schoolId, 403);
        abort_unless((int) $subject->school_id === $schoolId, 403);
        abort_unless((int) $term->academic_session_id === (int) $class->academic_session_id, 400);

        if (!Schema::hasTable('student_subject_exclusions')) {
            return response()->json([
                'message' => 'Student subject exclusions table is missing. Run migrations first.',
            ], 422);
        }

        $termSubject = TermSubject::query()
            ->where('class_id', (int) $class->id)
            ->where('term_id', (int) $term->id)
            ->where('subject_id', (int) $subject->id)
            ->when(Schema::hasColumn('term_subjects', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->first();

        if (!$termSubject) {
            return response()->json([
                'message' => 'Subject is not mapped to the selected class and term.',
            ], 404);
        }

        $studentQuery = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->leftJoin('class_departments', 'class_departments.id', '=', 'enrollments.department_id')
            ->where('enrollments.class_id', (int) $class->id)
            ->where('enrollments.term_id', (int) $term->id)
            ->where('students.school_id', $schoolId)
            ->select([
                'students.id as student_id',
                'users.name as student_name',
                'users.username as student_username',
                'class_departments.name as department_name',
            ])
            ->distinct()
            ->orderBy('users.name');

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $studentQuery->where('enrollments.school_id', $schoolId);
        }

        $students = $studentQuery->get();
        $studentIds = $students->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        $excludedStudentIds = [];
        if (!empty($studentIds)) {
            $excludedStudentIds = StudentSubjectExclusion::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $class->academic_session_id)
                ->where('class_id', (int) $class->id)
                ->where('subject_id', (int) $subject->id)
                ->whereIn('student_id', $studentIds)
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return response()->json([
            'data' => $students->map(function ($student) use ($excludedStudentIds) {
                $studentId = (int) $student->student_id;
                $offering = !in_array($studentId, $excludedStudentIds, true);

                return [
                    'student_id' => $studentId,
                    'student_name' => (string) $student->student_name,
                    'student_username' => (string) $student->student_username,
                    'department_name' => $student->department_name,
                    'offering' => $offering,
                ];
            })->values()->all(),
            'meta' => [
                'class_id' => (int) $class->id,
                'class_name' => (string) $class->name,
                'term_id' => (int) $term->id,
                'term_name' => (string) $term->name,
                'subject_id' => (int) $subject->id,
                'subject_name' => (string) $subject->name,
            ],
        ]);
    }

    /**
     * PATCH /api/school-admin/classes/{class}/terms/{term}/subjects/{subject}/students/{student}/offering
     * Toggle if a student offers this subject for the whole class session.
     */
    public function setStudentSubjectOffering(
        Request $request,
        SchoolClass $class,
        Term $term,
        Subject $subject,
        Student $student
    ) {
        $schoolId = (int) $request->user()->school_id;

        abort_unless((int) $class->school_id === $schoolId, 403);
        abort_unless((int) $term->school_id === $schoolId, 403);
        abort_unless((int) $subject->school_id === $schoolId, 403);
        abort_unless((int) $student->school_id === $schoolId, 403);
        abort_unless((int) $term->academic_session_id === (int) $class->academic_session_id, 400);

        if (!Schema::hasTable('student_subject_exclusions')) {
            return response()->json([
                'message' => 'Student subject exclusions table is missing. Run migrations first.',
            ], 422);
        }

        $payload = $request->validate([
            'offering' => 'required|boolean',
        ]);
        $offering = (bool) $payload['offering'];

        $isEnrolledQuery = Enrollment::query()
            ->where('class_id', (int) $class->id)
            ->where('term_id', (int) $term->id)
            ->where('student_id', (int) $student->id);
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $isEnrolledQuery->where('school_id', $schoolId);
        }

        $isEnrolled = $isEnrolledQuery->exists();
        $isInClassSession = DB::table('class_students')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $class->academic_session_id)
            ->where('class_id', (int) $class->id)
            ->where('student_id', (int) $student->id)
            ->exists();

        if (!$isEnrolled && !$isInClassSession) {
            return response()->json([
                'message' => 'Student is not assigned to the selected class/session.',
            ], 422);
        }

        $termSubjectIds = $this->sessionTermSubjectIds($schoolId, $class, $subject);
        if (empty($termSubjectIds)) {
            return response()->json([
                'message' => 'Subject is not mapped for this class session.',
            ], 404);
        }

        $deletedResults = 0;
        DB::transaction(function () use (
            $offering,
            $schoolId,
            $class,
            $subject,
            $student,
            $termSubjectIds,
            &$deletedResults
        ) {
            if ($offering) {
                StudentSubjectExclusion::query()
                    ->where('school_id', $schoolId)
                    ->where('academic_session_id', (int) $class->academic_session_id)
                    ->where('class_id', (int) $class->id)
                    ->where('subject_id', (int) $subject->id)
                    ->where('student_id', (int) $student->id)
                    ->delete();
                return;
            }

            StudentSubjectExclusion::query()->updateOrCreate([
                'school_id' => $schoolId,
                'academic_session_id' => (int) $class->academic_session_id,
                'class_id' => (int) $class->id,
                'subject_id' => (int) $subject->id,
                'student_id' => (int) $student->id,
            ], []);

            $deletedResults = DB::table('results')
                ->where('school_id', $schoolId)
                ->where('student_id', (int) $student->id)
                ->whereIn('term_subject_id', $termSubjectIds)
                ->delete();
        });

        return response()->json([
            'message' => $offering
                ? 'Student restored to this subject for the class session.'
                : 'Student removed from this subject for the class session.',
            'meta' => [
                'offering' => $offering,
                'deleted_results' => (int) $deletedResults,
            ],
        ]);
    }

    private function sessionTermSubjectIds(int $schoolId, SchoolClass $class, Subject $subject): array
    {
        $sessionTermIds = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $class->academic_session_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($sessionTermIds)) {
            return [];
        }

        return TermSubject::query()
            ->where('class_id', (int) $class->id)
            ->where('subject_id', (int) $subject->id)
            ->whereIn('term_id', $sessionTermIds)
            ->when(Schema::hasColumn('term_subjects', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

        if (!$class) {
            return response()->json(['message' => 'Class not found for this exam mapping'], 404);
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
