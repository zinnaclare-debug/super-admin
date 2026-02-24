<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
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

        $currentTeacher = null;
        if ($class->class_teacher_user_id) {
            $currentTeacher = User::where('id', $class->class_teacher_user_id)
                ->where('school_id', $schoolId)
                ->select('id', 'name', 'email')
                ->first();
        }

        return response()->json([
            'data' => $teachers,
            'meta' => [
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'level' => $class->level,
                ],
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
            'teacher_user_id' => 'required|integer|exists:users,id'
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

        $class->update(['class_teacher_user_id' => $teacher->id]);

        return response()->json([
            'message' => 'Teacher assigned',
            'data' => [
                'class_id' => $class->id,
                'class_teacher_user_id' => $class->class_teacher_user_id,
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                ]
            ]
        ]);
    }

    // PATCH /api/school-admin/classes/{class}/unassign-teacher
    public function unassignTeacher(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

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

        DB::transaction(function () use ($schoolId, $class, $payload, $sessionTermIds) {
            foreach ($payload['student_user_ids'] as $studentUserId) {
                // resolve student record for this user
                $student = \App\Models\Student::where('user_id', $studentUserId)
                    ->where('school_id', $schoolId)
                    ->first();

                if (!$student) continue;

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
                    }
                }
            }
        });

        return response()->json(['message' => 'Students enrolled for all terms in this session']);
    }

    // GET /api/school-admin/classes/{class}/terms/{term}/courses  (placeholder)
    public function termCourses(Request $request, SchoolClass $class, $termId)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        // TODO: replace with real courses table later
        return response()->json(['data' => []]);
    }
}
