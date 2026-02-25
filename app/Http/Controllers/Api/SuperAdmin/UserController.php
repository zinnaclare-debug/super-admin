<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    private function resolveCurrentTermId(int $schoolId): ?int
    {
        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) return null;

        $base = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id);

        if (Schema::hasColumn('terms', 'is_current')) {
            $current = (clone $base)->where('is_current', true)->first();
            if ($current) return (int) $current->id;
        }

        $fallback = (clone $base)->orderBy('id')->first();
        return $fallback ? (int) $fallback->id : null;
    }

    /**
     * List users (for assigning school admins)
     */
    public function index()
    {
        return response()->json([
            'data' => User::select('id', 'name', 'email', 'role', 'school_id')->get()
        ]);
    }

    /**
     * Create a school admin
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|min:6',
            'school_id' => 'required|exists:schools,id',
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'role'      => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $validated['school_id'],
        ]);

        return response()->json([
            'message' => 'School admin created successfully',
            'data' => $user,
        ], 201);
    }

    /**
     * Reset password for a school admin user.
     * POST /api/super-admin/users/{user}/reset-password
     */
    public function resetSchoolAdminPassword(Request $request, User $user)
    {
        if ($user->role !== User::ROLE_SCHOOL_ADMIN) {
            return response()->json([
                'message' => 'Only school admin passwords can be reset here.',
            ], 422);
        }

        $payload = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->password = Hash::make($payload['password']);
        $user->save();

        return response()->json([
            'message' => 'School admin password reset successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'school_id' => $user->school_id,
            ],
        ]);
    }

    /**
     * List students in a school with level counts.
     * GET /api/super-admin/schools/{school}/students-by-level?level=primary
     */
    public function studentsByLevel(Request $request, School $school)
    {
        $payload = $request->validate([
            'level' => 'nullable|string|max:60',
        ]);

        $schoolId = (int) $school->id;
        $currentTermId = $this->resolveCurrentTermId($schoolId);

        $baseQuery = Student::query()
            ->join('users', 'users.id', '=', 'students.user_id')
            ->leftJoin('enrollments', function ($join) use ($schoolId, $currentTermId) {
                $join->on('enrollments.student_id', '=', 'students.id');
                if (Schema::hasColumn('enrollments', 'school_id')) {
                    $join->where('enrollments.school_id', '=', $schoolId);
                }
                if ($currentTermId) {
                    $join->where('enrollments.term_id', '=', $currentTermId);
                }
            })
            ->leftJoin('classes', 'classes.id', '=', 'enrollments.class_id')
            ->where('students.school_id', $schoolId)
            ->where('users.role', 'student');

        $allRows = (clone $baseQuery)
            ->select([
                'students.id as student_id',
                'users.name as student_name',
                'classes.level as class_level',
            ])
            ->orderBy('users.name')
            ->get();

        $counts = [];
        foreach ($allRows as $row) {
            $lvl = strtolower((string) ($row->class_level ?? ''));
            if ($lvl !== '') {
                $counts[$lvl] = (int) ($counts[$lvl] ?? 0) + 1;
            }
        }

        $filteredRows = $allRows;
        if (!empty($payload['level'])) {
            $levelFilter = strtolower(trim((string) $payload['level']));
            $filteredRows = $allRows->filter(function ($row) use ($payload) {
                $levelFilter = strtolower(trim((string) $payload['level']));
                return strtolower((string) ($row->class_level ?? '')) === $levelFilter;
            })->values();
        }

        $students = $filteredRows->map(function ($row) {
            return [
                'student_id' => (int) $row->student_id,
                'name' => $row->student_name,
                'level' => $row->class_level ?: 'unassigned',
            ];
        })->values();

        $levels = collect($counts)
            ->map(function ($count, $key) {
                $label = ucwords(str_replace('_', ' ', (string) $key));
                return ['key' => (string) $key, 'label' => $label, 'count' => (int) $count];
            })
            ->sortBy('label')
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'school' => [
                    'id' => $school->id,
                    'name' => $school->name,
                ],
                'levels' => $levels,
                'students' => $students,
            ],
        ]);
    }
}
