<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\ClassDepartment;
use App\Models\LevelDepartment;
use App\Models\User;

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

        $this->syncClassDepartmentsFromLevel($schoolId, $class);

        $departmentIds = $rows
            ->pluck('department_id')
            ->filter(fn ($id) => $id !== null)
            ->unique()
            ->values();

        if ($departmentIds->isNotEmpty()) {
            $validDepartmentCount = ClassDepartment::where('school_id', $schoolId)
                ->where('class_id', $class->id)
                ->whereIn('id', $departmentIds->all())
                ->count();

            if ($validDepartmentCount !== $departmentIds->count()) {
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

        return DB::transaction(function () use ($rows, $class, $schoolId, $sessionTermIds) {

            $inserted = [];
            $skippedDuplicates = [];

            foreach ($rows as $row) {
                $studentId = (int) $row['student_id'];
                $departmentId = $row['department_id'];

                // ensure student belongs to this school
                $student = Student::where('id', $studentId)
                    ->where('school_id', $schoolId)
                    ->first();

                if (!$student) continue;

                $studentUser = User::where('id', $student->user_id)->first(['id', 'name', 'email']);
                if (!$studentUser) {
                    continue;
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
                    $exists = Enrollment::where([
                        'student_id' => $studentId,
                        'class_id' => $class->id,
                        'term_id' => $sessionTermId,
                    ])->exists();

                    if ($exists) {
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

        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 15);

        $q = Enrollment::where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->with(['student.user', 'department']);

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

        $this->syncClassDepartmentsFromLevel($schoolId, $class);

        $departments = ClassDepartment::where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->orderBy('name')
            ->get(['id', 'name']);

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

    private function syncClassDepartmentsFromLevel(int $schoolId, SchoolClass $class): void
    {
        $levelDepartments = LevelDepartment::where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('level', $class->level)
            ->get(['name']);

        foreach ($levelDepartments as $dept) {
            ClassDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'class_id' => $class->id,
                'name' => $dept->name,
            ]);
        }
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
}
