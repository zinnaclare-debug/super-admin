<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassProgressController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        $schoolId = (int) $user->school_id;
        $data = $request->validate([
            'class_id' => 'nullable|integer',
            'term_id' => 'nullable|integer',
        ]);

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json(['data' => null, 'message' => 'No current session'], 200);
        }

        $classes = SchoolClass::query()
            ->leftJoin('users as class_teachers', 'class_teachers.id', '=', 'classes.class_teacher_user_id')
            ->where('classes.school_id', $schoolId)
            ->where('classes.academic_session_id', $session->id)
            ->orderBy('classes.level')
            ->orderBy('classes.name')
            ->get([
                'classes.id',
                'classes.name',
                'classes.level',
                'classes.academic_session_id',
                'classes.class_teacher_user_id',
                'class_teachers.name as class_teacher_name',
            ]);

        $selectedClass = !empty($data['class_id'])
            ? $classes->firstWhere('id', (int) $data['class_id'])
            : $classes->first();

        if (!$selectedClass) {
            return response()->json(['data' => null, 'message' => 'No classes found for current session'], 200);
        }

        $terms = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'name', 'academic_session_id']);

        $selectedTerm = !empty($data['term_id'])
            ? $terms->firstWhere('id', (int) $data['term_id'])
            : $terms->first();

        if (!$selectedTerm) {
            return response()->json(['data' => null, 'message' => 'No terms found for current session'], 422);
        }

        $canUseDepartments = Schema::hasTable('class_departments');

        $enrollmentsQuery = Enrollment::query()
            ->where('enrollments.class_id', $selectedClass->id)
            ->where('enrollments.term_id', $selectedTerm->id)
            ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
                $q->where('enrollments.school_id', $schoolId);
            })
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name');

        if ($canUseDepartments) {
            $enrollmentsQuery->leftJoin('class_departments', function ($join) use ($schoolId) {
                $join->on('class_departments.id', '=', 'enrollments.department_id');
                if (Schema::hasColumn('class_departments', 'school_id')) {
                    $join->where('class_departments.school_id', '=', $schoolId);
                }
            });
        }

        $columns = [
            'students.id as student_id',
            'users.name as student_name',
        ];
        if ($canUseDepartments) {
            $columns[] = 'class_departments.name as department_name';
        }

        $enrollments = $enrollmentsQuery->get($columns);

        $studentIds = $enrollments
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $resultCompletionMap = $this->buildResultCompletionMap(
            $schoolId,
            (int) $session->id,
            (int) $selectedClass->id,
            (int) $selectedTerm->id,
            $studentIds
        );

        $commentCompletedIds = empty($studentIds)
            ? []
            : DB::table('student_attendances')
                ->where('school_id', $schoolId)
                ->where('class_id', $selectedClass->id)
                ->where('term_id', $selectedTerm->id)
                ->whereIn('student_id', $studentIds)
                ->whereNotNull('comment')
                ->whereRaw("TRIM(comment) <> ''")
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

        $behaviourCompletedIds = empty($studentIds) || !Schema::hasTable('student_behaviour_ratings')
            ? []
            : DB::table('student_behaviour_ratings')
                ->where('school_id', $schoolId)
                ->where('class_id', $selectedClass->id)
                ->where('term_id', $selectedTerm->id)
                ->whereIn('student_id', $studentIds)
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

        $commentCompletedLookup = array_fill_keys($commentCompletedIds, true);
        $behaviourCompletedLookup = array_fill_keys($behaviourCompletedIds, true);

        $students = $enrollments->values()->map(function ($row, $index) use ($resultCompletionMap, $commentCompletedLookup, $behaviourCompletedLookup) {
            $studentId = (int) $row->student_id;
            return [
                'sn' => $index + 1,
                'student_id' => $studentId,
                'student_name' => (string) $row->student_name,
                'department_name' => trim((string) ($row->department_name ?? '')),
                'result_status' => $resultCompletionMap[$studentId] ?? 'incomplete',
                'comment_status' => isset($commentCompletedLookup[$studentId]) ? 'completed' : 'incomplete',
                'behaviour_status' => isset($behaviourCompletedLookup[$studentId]) ? 'completed' : 'incomplete',
            ];
        })->all();

        $classOptions = $classes->values()->map(fn ($class) => [
            'id' => (int) $class->id,
            'name' => (string) $class->name,
            'level' => (string) $class->level,
            'academic_session_id' => (int) $class->academic_session_id,
            'class_teacher_user_id' => $class->class_teacher_user_id ? (int) $class->class_teacher_user_id : null,
            'class_teacher_name' => $class->class_teacher_name ? (string) $class->class_teacher_name : '',
        ])->all();

        return response()->json([
            'data' => [
                'session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'classes' => $classOptions,
                'terms' => $terms,
                'selected_class_id' => (int) $selectedClass->id,
                'selected_term_id' => (int) $selectedTerm->id,
                'selected_class_name' => (string) $selectedClass->name,
                'selected_class_level' => (string) $selectedClass->level,
                'selected_class_teacher_name' => $selectedClass->class_teacher_name ? (string) $selectedClass->class_teacher_name : '',
                'selected_term_name' => (string) $selectedTerm->name,
                'students' => $students,
                'summary' => [
                    'class_count' => count($classOptions),
                    'student_count' => count($students),
                    'results_completed' => count(array_filter($students, fn ($item) => ($item['result_status'] ?? '') === 'completed')),
                    'comments_completed' => count(array_filter($students, fn ($item) => ($item['comment_status'] ?? '') === 'completed')),
                    'behaviour_completed' => count(array_filter($students, fn ($item) => ($item['behaviour_status'] ?? '') === 'completed')),
                ],
            ],
        ]);
    }

    private function buildResultCompletionMap(int $schoolId, int $sessionId, int $classId, int $termId, array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $supportsExclusions = Schema::hasTable('student_subject_exclusions')
            && Schema::hasColumn('student_subject_exclusions', 'student_id')
            && Schema::hasColumn('student_subject_exclusions', 'school_id')
            && Schema::hasColumn('student_subject_exclusions', 'academic_session_id')
            && Schema::hasColumn('student_subject_exclusions', 'class_id')
            && Schema::hasColumn('student_subject_exclusions', 'subject_id');

        $query = DB::table('enrollments')
            ->join('term_subjects', function ($join) {
                $join->on('term_subjects.class_id', '=', 'enrollments.class_id')
                    ->on('term_subjects.term_id', '=', 'enrollments.term_id');
            })
            ->leftJoin('results', function ($join) use ($schoolId) {
                $join->on('results.term_subject_id', '=', 'term_subjects.id')
                    ->on('results.student_id', '=', 'enrollments.student_id')
                    ->where('results.school_id', '=', $schoolId);
            })
            ->where('enrollments.class_id', $classId)
            ->where('enrollments.term_id', $termId)
            ->whereIn('enrollments.student_id', $studentIds)
            ->where('term_subjects.school_id', $schoolId);

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $query->where('enrollments.school_id', $schoolId);
        }

        if ($supportsExclusions) {
            $query
                ->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $sessionId) {
                    $join->on('student_subject_exclusions.student_id', '=', 'enrollments.student_id')
                        ->on('student_subject_exclusions.class_id', '=', 'enrollments.class_id')
                        ->on('student_subject_exclusions.subject_id', '=', 'term_subjects.subject_id')
                        ->where('student_subject_exclusions.school_id', '=', $schoolId)
                        ->where('student_subject_exclusions.academic_session_id', '=', $sessionId);
                })
                ->whereNull('student_subject_exclusions.student_id');
        }

        $snapshots = $query
            ->groupBy('enrollments.student_id')
            ->selectRaw('enrollments.student_id as student_id')
            ->selectRaw('COUNT(DISTINCT term_subjects.id) as assigned_subject_count')
            ->selectRaw("SUM(CASE WHEN results.id IS NOT NULL AND (results.ca IS NOT NULL OR results.exam IS NOT NULL OR (results.created_at IS NOT NULL AND results.updated_at IS NOT NULL AND results.updated_at <> results.created_at)) THEN 1 ELSE 0 END) as graded_subject_count")
            ->get();

        $map = [];
        foreach ($studentIds as $studentId) {
            $map[(int) $studentId] = 'incomplete';
        }

        foreach ($snapshots as $snapshot) {
            $studentId = (int) $snapshot->student_id;
            $assigned = (int) ($snapshot->assigned_subject_count ?? 0);
            $graded = (int) ($snapshot->graded_subject_count ?? 0);
            $map[$studentId] = $assigned > 0 && $graded >= $assigned ? 'completed' : 'incomplete';
        }

        return $map;
    }
}
