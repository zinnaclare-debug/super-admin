<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\ClassDepartment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassManagementController extends Controller
{
    // GET /api/school-admin/classes/{class}/eligible-teachers
    public function eligibleTeachers(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $this->syncClassDepartmentsFromLevel((int) $schoolId, $class);

        $level = $class->level; // nursery|primary|secondary

        $teachers = User::query()
            ->where('school_id', $schoolId)
            ->where('role', 'staff')
            ->whereHas('staffProfile', function ($q) use ($level) {
                $q->where('education_level', $level);
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $departmentsQuery = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->orderBy('name');
        if (Schema::hasColumn('class_departments', 'class_teacher_user_id')) {
            $departmentsQuery->addSelect(['id', 'name', 'class_teacher_user_id']);
        } else {
            $departmentsQuery->addSelect(['id', 'name']);
        }
        $departments = $departmentsQuery->get();

        $selectedDepartmentId = null;
        $selectedDepartment = null;
        if ($departments->isNotEmpty()) {
            $requestedDepartmentId = (int) $request->query('department_id', 0);
            $selectedDepartment = $requestedDepartmentId > 0
                ? $departments->firstWhere('id', $requestedDepartmentId)
                : $departments->first();
            if (!$selectedDepartment) {
                return response()->json(['message' => 'Invalid department selected for this class'], 422);
            }
            $selectedDepartmentId = (int) $selectedDepartment->id;
        }

        $canUseDepartmentTeacher = Schema::hasColumn('class_departments', 'class_teacher_user_id');
        $currentTeacher = null;
        if ($selectedDepartmentId !== null && $canUseDepartmentTeacher) {
            $teacherUserId = ClassDepartment::query()
                ->where('school_id', $schoolId)
                ->where('class_id', $class->id)
                ->where('id', $selectedDepartmentId)
                ->value('class_teacher_user_id');

            if ($teacherUserId) {
                $currentTeacher = User::where('id', $teacherUserId)
                    ->where('school_id', $schoolId)
                    ->select('id', 'name', 'email')
                    ->first();
            }
        } elseif ($class->class_teacher_user_id) {
            $currentTeacher = User::where('id', $class->class_teacher_user_id)
                ->where('school_id', $schoolId)
                ->select('id', 'name', 'email')
                ->first();
        }

        $departmentRows = $departments->map(function ($department) use ($schoolId, $canUseDepartmentTeacher) {
            $teacher = null;
            if ($canUseDepartmentTeacher && $department->class_teacher_user_id) {
                $teacher = User::query()
                    ->where('id', (int) $department->class_teacher_user_id)
                    ->where('school_id', $schoolId)
                    ->select('id', 'name', 'email')
                    ->first();
            }

            return [
                'id' => (int) $department->id,
                'name' => (string) $department->name,
                'current_teacher' => $teacher ? [
                    'id' => (int) $teacher->id,
                    'name' => (string) $teacher->name,
                    'email' => $teacher->email,
                ] : null,
            ];
        })->values()->all();

        return response()->json([
            'data' => $teachers,
            'meta' => [
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'level' => $class->level,
                ],
                'has_departments' => !empty($departmentRows),
                'selected_department_id' => $selectedDepartmentId,
                'departments' => $departmentRows,
                'current_teacher' => $currentTeacher,
            ],
        ]);
    }

    // PATCH /api/school-admin/classes/{class}/assign-teacher
    public function assignTeacher(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $payload = $request->validate([
            'teacher_user_id' => 'required|integer|exists:users,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
        ]);

        $teacher = User::where('id', $payload['teacher_user_id'])
            ->where('school_id', $schoolId)
            ->where('role', 'staff')
            ->firstOrFail();

        // ensure teacher matches class level
        $staff = Staff::where('user_id', $teacher->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$staff || $staff->education_level !== $class->level) {
            return response()->json(['message' => 'Teacher level does not match this class'], 422);
        }

        $this->syncClassDepartmentsFromLevel((int) $schoolId, $class);
        $departments = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($departments->isNotEmpty()) {
            if (!Schema::hasColumn('class_departments', 'class_teacher_user_id')) {
                return response()->json(['message' => 'Department teacher support is not ready. Run migrations.'], 422);
            }

            $departmentId = (int) ($payload['department_id'] ?? 0);
            if ($departmentId < 1) {
                return response()->json(['message' => 'Select a department for class teacher assignment'], 422);
            }

            $department = $departments->firstWhere('id', $departmentId);
            if (!$department) {
                return response()->json(['message' => 'Invalid department selected for this class'], 422);
            }

            ClassDepartment::query()
                ->where('school_id', $schoolId)
                ->where('class_id', $class->id)
                ->where('id', $departmentId)
                ->update(['class_teacher_user_id' => $teacher->id]);

            return response()->json([
                'message' => 'Department class teacher assigned',
                'data' => [
                    'class_id' => (int) $class->id,
                    'department_id' => $departmentId,
                    'class_teacher_user_id' => (int) $teacher->id,
                    'teacher' => [
                        'id' => (int) $teacher->id,
                        'name' => (string) $teacher->name,
                        'email' => $teacher->email,
                    ],
                ],
            ]);
        }

        $class->update(['class_teacher_user_id' => (int) $teacher->id]);

        return response()->json([
            'message' => 'Teacher assigned',
            'data' => [
                'class_id' => $class->id,
                'class_teacher_user_id' => $class->class_teacher_user_id,
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                ],
            ],
        ]);
    }

    // PATCH /api/school-admin/classes/{class}/unassign-teacher
    public function unassignTeacher(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $payload = $request->validate([
            'department_id' => 'nullable|integer|exists:class_departments,id',
        ]);

        $this->syncClassDepartmentsFromLevel((int) $schoolId, $class);
        $departments = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($departments->isNotEmpty()) {
            if (!Schema::hasColumn('class_departments', 'class_teacher_user_id')) {
                return response()->json(['message' => 'Department teacher support is not ready. Run migrations.'], 422);
            }

            $departmentId = (int) ($payload['department_id'] ?? 0);
            if ($departmentId < 1) {
                return response()->json(['message' => 'Select a department to unassign class teacher'], 422);
            }

            $department = $departments->firstWhere('id', $departmentId);
            if (!$department) {
                return response()->json(['message' => 'Invalid department selected for this class'], 422);
            }

            ClassDepartment::query()
                ->where('school_id', $schoolId)
                ->where('class_id', $class->id)
                ->where('id', $departmentId)
                ->update(['class_teacher_user_id' => null]);

            return response()->json([
                'message' => 'Department class teacher unassigned',
                'data' => [
                    'class_id' => (int) $class->id,
                    'department_id' => $departmentId,
                ],
            ]);
        }

        $class->update(['class_teacher_user_id' => null]);

        return response()->json(['message' => 'Class teacher unassigned']);
    }

    // GET /api/school-admin/classes/{class}/students?search=
    public function listStudents(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 15);

        $q = User::query()
            ->with('studentProfile:id,user_id,school_id')
            ->where('school_id', $schoolId)
            ->where('role', 'student')
            ->select('id', 'name', 'email')
            ->orderBy('name');

        if ($search !== '') {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $page = (int) max(1, $request->query('page', 1));
        $p = $q->paginate($perPage, ['*'], 'page', $page);

        $items = $p->map(function ($user) {
            return [
                'id' => $user->id,
                'student_id' => $user->studentProfile?->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        });

        return response()->json(['data' => $items->all(), 'meta' => [
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
        ]]);
    }

    // POST /api/school-admin/classes/{class}/enroll
    public function enroll(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $payload = $request->validate([
            'student_user_ids' => 'required|array',
            'student_user_ids.*' => 'integer|exists:users,id',
        ]);

        $sessionTermIds = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $currentSessionTermId = $this->resolveCurrentSessionTermId($schoolId, (int) $class->academic_session_id);
        $skipped = [];
        $insertedCount = 0;

        DB::transaction(function () use (
            $schoolId,
            $class,
            $payload,
            $sessionTermIds,
            $currentSessionTermId,
            &$skipped,
            &$insertedCount
        ) {
            foreach ($payload['student_user_ids'] as $studentUserId) {
                // resolve student record for this user
                $student = \App\Models\Student::where('user_id', $studentUserId)
                    ->where('school_id', $schoolId)
                    ->first();

                if (!$student) continue;

                if ($currentSessionTermId) {
                    $existingClassId = $this->resolveCurrentTermEnrollmentClassId(
                        $schoolId,
                        (int) $student->id,
                        (int) $currentSessionTermId
                    );

                    if ($existingClassId && (int) $existingClassId !== (int) $class->id) {
                        $skipped[] = [
                            'student_id' => (int) $student->id,
                            'user_id' => (int) $studentUserId,
                            'reason' => 'Student already has a class in the current term/session',
                        ];
                        continue;
                    }
                }

                // insert into class_students (class-level enrollment)
                DB::table('class_students')->updateOrInsert([
                    'school_id' => $schoolId,
                    'academic_session_id' => $class->academic_session_id,
                    'class_id' => $class->id,
                    'student_id' => $student->id,
                ], [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);

                // Mirror enrollment across all terms in this academic session.
                foreach ($sessionTermIds as $termId) {
                    $where = [
                        'student_id' => $student->id,
                        'class_id' => $class->id,
                        'term_id' => $termId,
                    ];
                    if (Schema::hasColumn('enrollments', 'school_id')) {
                        $where['school_id'] = $schoolId;
                    }

                    $exists = DB::table('enrollments')->where($where)->exists();
                    if (! $exists) {
                        DB::table('enrollments')->insert([
                            ...$where,
                            'department_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $insertedCount++;
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Students enrolled for all terms in this session',
            'count' => $insertedCount,
            'skipped' => $skipped,
        ]);
    }

    // GET /api/school-admin/classes/{class}/terms/{term}/courses  (placeholder)
    public function termCourses(Request $request, SchoolClass $class, $termId)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        // TODO: replace with real courses table later
        return response()->json(['data' => []]);
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
        $query = DB::table('enrollments')
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->orderByDesc('id');

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $query->where('school_id', $schoolId);
        }

        $classId = $query->value('class_id');
        return $classId ? (int) $classId : null;
    }

    private function syncClassDepartmentsFromLevel(int $schoolId, SchoolClass $class): void
    {
        if (!Schema::hasTable('level_departments') || !Schema::hasTable('class_departments')) {
            return;
        }

        $levelDepartments = DB::table('level_departments')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('level', $class->level)
            ->pluck('name');

        foreach ($levelDepartments as $name) {
            ClassDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'class_id' => $class->id,
                'name' => $name,
            ]);
        }
    }
}
