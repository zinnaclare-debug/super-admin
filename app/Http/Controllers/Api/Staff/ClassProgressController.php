<?php

namespace App\Http\Controllers\Api\Staff;

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
    private function resolveCurrentContext(int $schoolId, int $staffUserId, ?int $classId = null, ?int $termId = null): array
    {
        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return ['session' => null, 'class' => null, 'term' => null, 'classes' => collect(), 'terms' => collect()];
        }

        $directClassIds = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('class_teacher_user_id', $staffUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $departmentScopeByClass = [];
        $departmentNameScopeByClass = [];

        if (Schema::hasTable('class_departments') && Schema::hasColumn('class_departments', 'class_teacher_user_id')) {
            $departmentRows = DB::table('class_departments')
                ->join('classes', 'classes.id', '=', 'class_departments.class_id')
                ->where('classes.school_id', $schoolId)
                ->where('classes.academic_session_id', $session->id)
                ->where('class_departments.class_teacher_user_id', $staffUserId)
                ->get([
                    'class_departments.class_id',
                    'class_departments.id as department_id',
                    'class_departments.name as department_name',
                ]);

            foreach ($departmentRows as $row) {
                $cid = (int) $row->class_id;
                $did = (int) $row->department_id;
                $dname = trim((string) ($row->department_name ?? ''));

                if ($cid < 1 || $did < 1) {
                    continue;
                }

                $departmentScopeByClass[$cid] = $departmentScopeByClass[$cid] ?? [];
                $departmentScopeByClass[$cid][] = $did;

                if ($dname !== '') {
                    $departmentNameScopeByClass[$cid] = $departmentNameScopeByClass[$cid] ?? [];
                    $departmentNameScopeByClass[$cid][] = $dname;
                }
            }

            foreach ($departmentScopeByClass as $cid => $ids) {
                $departmentScopeByClass[$cid] = array_values(array_unique(array_map('intval', $ids)));
            }

            foreach ($departmentNameScopeByClass as $cid => $names) {
                $departmentNameScopeByClass[$cid] = array_values(array_unique(array_filter(array_map(
                    fn ($name) => trim((string) $name),
                    $names
                ))));
            }
        }

        $classIds = $directClassIds
            ->merge(array_keys($departmentScopeByClass))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $classes = $classIds->isEmpty()
            ? collect()
            : SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->whereIn('id', $classIds->all())
                ->orderBy('level')
                ->orderBy('name')
                ->get(['id', 'name', 'level', 'academic_session_id']);

        $selectedClass = $classId ? $classes->firstWhere('id', $classId) : $classes->first();

        $terms = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'name', 'academic_session_id']);

        $selectedTerm = $termId
            ? $terms->firstWhere('id', $termId)
            : $terms->first();

        return [
            'session' => $session,
            'class' => $selectedClass,
            'term' => $selectedTerm,
            'classes' => $classes,
            'terms' => $terms,
            'direct_class_ids' => $directClassIds->all(),
            'department_scope_by_class' => $departmentScopeByClass,
            'department_name_scope_by_class' => $departmentNameScopeByClass,
        ];
    }

    private function hasDirectClassAccess(array $ctx, int $classId): bool
    {
        $ids = array_map('intval', $ctx['direct_class_ids'] ?? []);
        return in_array($classId, $ids, true);
    }

    private function departmentScopeForClass(array $ctx, int $classId): array
    {
        $scopes = $ctx['department_scope_by_class'] ?? [];
        $raw = $scopes[$classId] ?? $scopes[(string) $classId] ?? [];
        return array_values(array_unique(array_map('intval', (array) $raw)));
    }

    private function departmentNamesForClass(array $ctx, int $classId): array
    {
        $scopes = $ctx['department_name_scope_by_class'] ?? [];
        $raw = $scopes[$classId] ?? $scopes[(string) $classId] ?? [];
        return array_values(array_unique(array_filter(array_map(
            fn ($name) => trim((string) $name),
            (array) $raw
        ))));
    }

    private function scopeLabelForClass(array $ctx, int $classId): string
    {
        if ($this->hasDirectClassAccess($ctx, $classId)) {
            return 'All students in class';
        }

        $departmentNames = $this->departmentNamesForClass($ctx, $classId);
        if (!empty($departmentNames)) {
            return 'Departments: ' . implode(', ', $departmentNames);
        }

        return 'Assigned students only';
    }

    public function status(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'staff', 403);

        $ctx = $this->resolveCurrentContext((int) $user->school_id, (int) $user->id);

        return response()->json([
            'data' => [
                'can_access' => (bool) $ctx['class'],
                'class_count' => $ctx['classes']->count(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'staff', 403);

        $schoolId = (int) $user->school_id;
        $data = $request->validate([
            'class_id' => 'nullable|integer',
            'term_id' => 'nullable|integer',
        ]);

        $ctx = $this->resolveCurrentContext(
            $schoolId,
            (int) $user->id,
            $data['class_id'] ?? null,
            $data['term_id'] ?? null
        );

        if (!$ctx['session']) {
            return response()->json(['data' => null, 'message' => 'No current session'], 200);
        }

        if (!$ctx['class']) {
            return response()->json(['data' => null, 'message' => 'Only class teachers can access class progress'], 403);
        }

        if (!$ctx['term']) {
            return response()->json(['data' => null, 'message' => 'No terms found for current session'], 422);
        }

        $canUseDepartments = Schema::hasTable('class_departments');

        $enrollmentsQuery = Enrollment::query()
            ->where('enrollments.class_id', $ctx['class']->id)
            ->where('enrollments.term_id', $ctx['term']->id)
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

        if (
            !$this->hasDirectClassAccess($ctx, (int) $ctx['class']->id)
            && Schema::hasColumn('enrollments', 'department_id')
        ) {
            $departmentIds = $this->departmentScopeForClass($ctx, (int) $ctx['class']->id);
            if (empty($departmentIds)) {
                return response()->json(['data' => null, 'message' => 'Only class teachers can access class progress'], 403);
            }
            $enrollmentsQuery->whereIn('enrollments.department_id', $departmentIds);
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
            (int) $ctx['session']->id,
            (int) $ctx['class']->id,
            (int) $ctx['term']->id,
            $studentIds
        );

        $commentCompletedIds = empty($studentIds)
            ? []
            : DB::table('student_attendances')
                ->where('school_id', $schoolId)
                ->where('class_id', $ctx['class']->id)
                ->where('term_id', $ctx['term']->id)
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
                ->where('class_id', $ctx['class']->id)
                ->where('term_id', $ctx['term']->id)
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

        $classOptions = $ctx['classes']->values()->map(function ($class) use ($ctx) {
            $classId = (int) $class->id;
            return [
                'id' => $classId,
                'name' => (string) $class->name,
                'level' => (string) $class->level,
                'academic_session_id' => (int) $class->academic_session_id,
                'department_names' => $this->departmentNamesForClass($ctx, $classId),
                'scope_label' => $this->scopeLabelForClass($ctx, $classId),
            ];
        })->all();

        $selectedDepartmentNames = $this->departmentNamesForClass($ctx, (int) $ctx['class']->id);

        return response()->json([
            'data' => [
                'session' => [
                    'id' => (int) $ctx['session']->id,
                    'session_name' => $ctx['session']->session_name,
                    'academic_year' => $ctx['session']->academic_year,
                ],
                'classes' => $classOptions,
                'terms' => $ctx['terms'],
                'selected_class_id' => (int) $ctx['class']->id,
                'selected_term_id' => (int) $ctx['term']->id,
                'selected_class_name' => (string) $ctx['class']->name,
                'selected_class_level' => (string) $ctx['class']->level,
                'selected_term_name' => (string) $ctx['term']->name,
                'selected_department_names' => $selectedDepartmentNames,
                'selected_scope_label' => $this->scopeLabelForClass($ctx, (int) $ctx['class']->id),
                'students' => $students,
                'summary' => [
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