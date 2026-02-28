<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Term;
use App\Models\ClassDepartment;
use App\Models\User;
use App\Support\ClassTemplateSchema;
use App\Support\DepartmentTemplateSync;

class EnrollmentController extends Controller
{
    /**
     * BULK ENROLL STUDENTS
     *
     * Accepts:
     * student_ids: [1,2,3]
     * department_id: optional
     */
    public function bulkEnroll(Request $request, SchoolClass $class, Term $term)
    {
        $schoolId = $request->user()->school_id;

        // -------------------------
        // MULTI TENANT SECURITY
        // -------------------------
        abort_unless($class->school_id === $schoolId, 403);
        abort_unless($term->school_id === $schoolId, 403);
        abort_unless((int) $term->academic_session_id === (int) $class->academic_session_id, 400);

        // -------------------------
        // VALIDATION
        // -------------------------
        $data = $request->validate([
            'student_ids' => 'sometimes|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
            'enrollments' => 'sometimes|array|min:1',
            'enrollments.*.student_id' => 'required|integer|exists:students,id',
            'enrollments.*.department_id' => 'nullable|integer|exists:class_departments,id',
        ]);

        $rows = collect($data['enrollments'] ?? [])->map(function ($row) {
            return [
                'student_id' => (int) ($row['student_id'] ?? 0),
                'department_id' => isset($row['department_id']) ? (int) $row['department_id'] : null,
            ];
        })->values();

        if ($rows->isEmpty()) {
            $rows = collect($data['student_ids'] ?? [])->map(function ($studentId) use ($data) {
                return [
                    'student_id' => (int) $studentId,
                    'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
                ];
            })->values();
        }

        if ($rows->isEmpty()) {
            return response()->json(['message' => 'Provide enrollments or student_ids'], 422);
        }

        $classDepartments = $this->loadTemplateScopedClassDepartments($schoolId, $class);
        $classDepartmentIds = $classDepartments
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $hasDepartments = !empty($classDepartmentIds);

        $contextDepartmentId = null;
        if (array_key_exists('department_id', $data) && $data['department_id'] !== null) {
            $contextDepartmentId = (int) $data['department_id'];
            if (!in_array($contextDepartmentId, $classDepartmentIds, true)) {
                return response()->json(['message' => 'Invalid department selected for this class'], 422);
            }
        }

        $rows = $rows->map(function ($row) use ($contextDepartmentId) {
            if ($contextDepartmentId !== null) {
                $row['department_id'] = $contextDepartmentId;
            }
            if (empty($row['department_id'])) {
                $row['department_id'] = null;
            }
            return $row;
        })->values();

        if ($hasDepartments && $rows->contains(fn ($row) => empty($row['department_id']))) {
            return response()->json(['message' => 'Select department for all selected students'], 422);
        }

        $departmentIds = $rows
            ->pluck('department_id')
            ->filter(fn ($id) => $id !== null)
            ->unique()
            ->values();

        if ($departmentIds->isNotEmpty()) {
            $invalid = $departmentIds
                ->reject(fn ($id) => in_array((int) $id, $classDepartmentIds, true))
                ->values();
            if ($invalid->isNotEmpty()) {
                return response()->json(['message' => 'Invalid department selected for this class'], 422);
            }
        }

        $sessionTermIds = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($sessionTermIds)) {
            return response()->json(['message' => 'No terms found for this class session'], 422);
        }

        $currentSessionTermId = $this->resolveCurrentSessionTermId($schoolId, (int) $class->academic_session_id);

        return DB::transaction(function () use ($rows, $class, $schoolId, $sessionTermIds, $currentSessionTermId) {

            $inserted = [];
            $updatedDepartmentRows = 0;
            $skippedDuplicates = [];

            foreach ($rows as $row) {
                $studentId = (int) $row['student_id'];
                $departmentId = $row['department_id'] ? (int) $row['department_id'] : null;

                // ensure student belongs to this school
                $student = Student::where('id', $studentId)
                    ->where('school_id', $schoolId)
                    ->first();

                if (!$student) continue;

                $studentUser = User::where('id', $student->user_id)->first(['id', 'name', 'email']);
                if (!$studentUser) {
                    continue;
                }

                if ($currentSessionTermId) {
                    $existingClassId = $this->resolveCurrentTermEnrollmentClassId(
                        $schoolId,
                        (int) $student->id,
                        (int) $currentSessionTermId
                    );

                    if ($existingClassId && (int) $existingClassId !== (int) $class->id) {
                        $skippedDuplicates[] = [
                            'student_id' => (int) $student->id,
                            'name' => $studentUser->name,
                            'email' => $studentUser->email,
                            'reason' => 'Student already has a class in the current term/session',
                        ];
                        continue;
                    }
                }

                if ($this->hasDuplicateNameOrEmailEnrollment(
                    $schoolId,
                    (int) $class->academic_session_id,
                    (int) $student->id,
                    (string) $studentUser->name,
                    $studentUser->email
                )) {
                    $skippedDuplicates[] = [
                        'student_id' => (int) $student->id,
                        'name' => $studentUser->name,
                        'email' => $studentUser->email,
                        'reason' => 'Name or email already enrolled in this session',
                    ];
                    continue;
                }

                DB::table('class_students')->updateOrInsert([
                    'school_id' => $schoolId,
                    'academic_session_id' => $class->academic_session_id,
                    'class_id' => $class->id,
                    'student_id' => $studentId,
                ], [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);

                foreach ($sessionTermIds as $sessionTermId) {
                    $enrollmentQuery = Enrollment::query()
                        ->where('student_id', $studentId)
                        ->where('class_id', $class->id)
                        ->where('term_id', $sessionTermId)
                        ->orderByDesc('id');
                    if (Schema::hasColumn('enrollments', 'school_id')) {
                        $enrollmentQuery->where('school_id', $schoolId);
                    }

                    $existingRows = $enrollmentQuery->get();
                    if ($existingRows->isNotEmpty()) {
                        $keeper = $existingRows->first();
                        if ((int) ($keeper->department_id ?? 0) !== (int) ($departmentId ?? 0)) {
                            $keeper->department_id = $departmentId;
                            $keeper->save();
                            $updatedDepartmentRows++;
                        }

                        $duplicateIds = $existingRows->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
                        if (!empty($duplicateIds)) {
                            Enrollment::query()->whereIn('id', $duplicateIds)->delete();
                        }
                        continue;
                    }

                    $enrollmentData = [
                        'student_id' => $studentId,
                        'class_id' => $class->id,
                        'term_id' => $sessionTermId,
                        'department_id' => $departmentId,
                    ];
                    if (Schema::hasColumn('enrollments', 'school_id')) {
                        $enrollmentData['school_id'] = $schoolId;
                    }

                    $inserted[] = Enrollment::create($enrollmentData);
                }
            }

            return response()->json([
                'message' => 'Enrollment completed for all terms in this session',
                'count' => count($inserted),
                'updated_department_rows' => $updatedDepartmentRows,
                'skipped_duplicates' => $skippedDuplicates,
                'data' => $inserted
            ]);
        });
    }

    /**
     * GET enrolled students for class + term
     */
    public function listEnrolled(Request $request, SchoolClass $class, Term $term)
    {
        $schoolId = $request->user()->school_id;

        abort_unless($class->school_id === $schoolId, 403);
        abort_unless($term->school_id === $schoolId, 403);
        abort_unless((int) $term->academic_session_id === (int) $class->academic_session_id, 400);

        $departmentId = $request->filled('department_id')
            ? (int) $request->query('department_id')
            : null;

        $departments = $this->loadTemplateScopedClassDepartments($schoolId, $class)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        if ($departmentId !== null && !in_array($departmentId, $departments, true)) {
            return response()->json(['message' => 'Invalid department selected for this class'], 422);
        }

        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 15);

        $q = Enrollment::where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->with(['student.user', 'department']);
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $q->where('school_id', $schoolId);
        }
        if ($departmentId !== null) {
            $q->where('department_id', $departmentId);
        }

        if ($search !== '') {
            $q->whereHas('student.user', function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $page = (int) max(1, $request->query('page', 1));
        $p = $q->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $items = $p->map(function ($e) {
            return [
                'id' => $e->id,
                'student_id' => $e->student_id,
                'student' => [
                    'id' => $e->student?->id,
                    'user_id' => $e->student?->user_id,
                    'name' => $e->student?->user?->name ?? null,
                    'email' => $e->student?->user?->email ?? null,
                ],
                'department' => $e->department ? [
                    'id' => $e->department->id,
                    'name' => $e->department->name,
                ] : null,
            ];
        });

        return response()->json(['data' => $items, 'meta' => [
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
            'selected_department_id' => $departmentId,
        ]]);
    }

    /**
     * GET departments for class + term
     */
    public function classDepartments(Request $request, SchoolClass $class, Term $term)
    {
        $schoolId = $request->user()->school_id;

        abort_unless($class->school_id === $schoolId, 403);
        abort_unless($term->school_id === $schoolId, 403);
        abort_unless((int) $term->academic_session_id === (int) $class->academic_session_id, 400);

        $departments = $this->loadTemplateScopedClassDepartments($schoolId, $class);

        return response()->json(['data' => $departments]);
    }

    /**
     * DELETE unenroll students in bulk
     * 
     * Accepts:
     * enrollment_ids: [1,2,3]
     */
    public function bulkUnenroll(Request $request, SchoolClass $class, Term $term)
    {
        $schoolId = $request->user()->school_id;

        abort_unless($class->school_id === $schoolId, 403);
        abort_unless($term->school_id === $schoolId, 403);
        abort_unless((int) $term->academic_session_id === (int) $class->academic_session_id, 400);

        $data = $request->validate([
            'enrollment_ids' => 'required|array|min:1',
            'enrollment_ids.*' => 'integer|exists:enrollments,id',
        ]);

        $studentIds = Enrollment::whereIn('id', $data['enrollment_ids'])
            ->where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $sessionTermIds = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->pluck('id')
            ->all();

        $deleted = empty($studentIds)
            ? 0
            : Enrollment::where('class_id', $class->id)
                ->whereIn('term_id', $sessionTermIds)
                ->whereIn('student_id', $studentIds)
                ->delete();

        if (!empty($studentIds)) {
            DB::table('class_students')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $class->academic_session_id)
                ->where('class_id', $class->id)
                ->whereIn('student_id', $studentIds)
                ->delete();
        }

        return response()->json([
            'message' => 'Students unenrolled for this class session',
            'count' => $deleted
        ]);
    }

    private function loadTemplateScopedClassDepartments(int $schoolId, SchoolClass $class)
    {
        $templateNames = $this->syncClassDepartmentsFromTemplates($schoolId, $class);
        $allowedNames = collect($templateNames)
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($allowedNames)) {
            return collect();
        }

        return ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn ($department) => in_array(
                strtolower(trim((string) $department->name)),
                $allowedNames,
                true
            ))
            ->values();
    }

    private function syncClassDepartmentsFromTemplates(int $schoolId, SchoolClass $class): array
    {
        $school = School::query()
            ->where('id', $schoolId)
            ->first(['id', 'class_templates', 'department_templates']);

        if (!$school) {
            return [];
        }

        $templateNames = DepartmentTemplateSync::classTemplateNamesForClass(
            $school->department_templates ?? [],
            ClassTemplateSchema::normalize($school->class_templates),
            (string) $class->level,
            (string) $class->name
        );

        foreach ($templateNames as $name) {
            $departmentName = trim((string) $name);
            if ($departmentName === '') {
                continue;
            }
            ClassDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'class_id' => $class->id,
                'name' => $departmentName,
            ]);
        }

        return $templateNames;
    }

    private function hasDuplicateNameOrEmailEnrollment(
        int $schoolId,
        int $academicSessionId,
        int $excludeStudentId,
        string $name,
        ?string $email
    ): bool {
        $query = Enrollment::query()
            ->join('students as s', 's.id', '=', 'enrollments.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('classes as c', 'c.id', '=', 'enrollments.class_id')
            ->where('s.school_id', $schoolId)
            ->where('c.academic_session_id', $academicSessionId)
            ->where('s.id', '!=', $excludeStudentId)
            ->where(function ($sub) use ($name, $email) {
                $sub->where('u.name', $name);
                if (filled($email)) {
                    $sub->orWhere('u.email', $email);
                }
            });

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $query->where('enrollments.school_id', $schoolId);
        }

        return $query->exists();
    }

    private function resolveCurrentSessionTermId(int $schoolId, int $academicSessionId): ?int
    {
        $isCurrentSession = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('id', $academicSessionId)
            ->where('status', 'current')
            ->exists();

        if (!$isCurrentSession) {
            return null;
        }

        $termQuery = DB::table('terms')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $academicSessionId);

        if (Schema::hasColumn('terms', 'is_current')) {
            $current = (clone $termQuery)->where('is_current', true)->value('id');
            if ($current) {
                return (int) $current;
            }
        }

        $fallback = (clone $termQuery)->orderBy('id')->value('id');
        return $fallback ? (int) $fallback : null;
    }

    private function resolveCurrentTermEnrollmentClassId(int $schoolId, int $studentId, int $termId): ?int
    {
        $query = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->orderByDesc('id');

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $query->where('school_id', $schoolId);
        }

        $classId = $query->value('class_id');
        return $classId ? (int) $classId : null;
    }
}
