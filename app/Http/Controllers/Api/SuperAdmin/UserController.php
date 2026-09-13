<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\ClassTemplateSchema;
use App\Support\UserCredentialStore;
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

        UserCredentialStore::sync(
            $user,
            (string) $validated['password'],
            (int) $request->user()->id
        );

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

        $this->validateDeleteCode($request);

        $payload = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->password = Hash::make($payload['password']);
        $user->save();

        UserCredentialStore::sync(
            $user,
            (string) $payload['password'],
            (int) $request->user()->id
        );

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

        $selectColumns = [
            'students.id as student_id',
            'users.name as student_name',
            'classes.level as class_level',
        ];
        $hasStudentEducationLevel = Schema::hasColumn('students', 'education_level');
        if ($hasStudentEducationLevel) {
            $selectColumns[] = 'students.education_level as student_level';
        }

        $allRows = (clone $baseQuery)
            ->select($selectColumns)
            ->orderBy('users.name')
            ->get();

        $counts = [];
        foreach ($allRows as $row) {
            $lvl = $this->normalizeLevelValue((string) ($row->class_level ?? ($row->student_level ?? '')));
            if ($lvl !== '') {
                $counts[$lvl] = (int) ($counts[$lvl] ?? 0) + 1;
            }
        }

        $filteredRows = $allRows;
        if (!empty($payload['level'])) {
            $levelFilter = $this->normalizeLevelValue((string) $payload['level']);
            $filteredRows = $allRows->filter(function ($row) use ($payload) {
                $levelFilter = $this->normalizeLevelValue((string) $payload['level']);
                $effectiveLevel = $this->normalizeLevelValue((string) ($row->class_level ?? ($row->student_level ?? '')));
                return $effectiveLevel === $levelFilter;
            })->values();
        }

        $students = $filteredRows->map(function ($row) {
            $effectiveLevelRaw = trim((string) ($row->class_level ?? ($row->student_level ?? '')));
            $effectiveLevel = $this->normalizeLevelValue($effectiveLevelRaw);
            return [
                'student_id' => (int) $row->student_id,
                'name' => $row->student_name,
                'level' => $effectiveLevel !== '' ? $effectiveLevel : 'unassigned',
            ];
        })->values();

        $templateLevelMap = collect(
            ClassTemplateSchema::activeLevelKeys(
                ClassTemplateSchema::normalize($school->class_templates)
            )
        )
            ->mapWithKeys(fn ($key) => [$key => 0])
            ->all();
        $mergedCounts = array_merge($templateLevelMap, $counts);

        $levels = collect($mergedCounts)
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

    // GET /api/super-admin/schools/{school}/reactivation-requests
    public function reactivationRequests(School $school)
    {
        $rows = Student::query()
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('students.school_id', (int) $school->id)
            ->where('students.exit_reason', 'left_school')
            ->whereNotNull('students.reactivation_requested_at')
            ->whereNull('students.reactivation_approved_at')
            ->select(['students.id as student_id', 'users.name', 'students.education_level', 'students.reactivation_requested_at'])
            ->orderBy('students.reactivation_requested_at')
            ->get()
            ->map(function ($row, int $index) {
                return [
                    'sn' => $index + 1,
                    'student_id' => (int) $row->student_id,
                    'name' => (string) $row->name,
                    'level' => $this->normalizeLevelValue((string) $row->education_level) ?: 'unassigned',
                    'requested_at' => $row->reactivation_requested_at,
                ];
            })->values();

        return response()->json(['data' => ['students' => $rows]]);
    }

    // POST /api/super-admin/students/{student}/approve-reactivation
    public function approveReactivation(Request $request, Student $student)
    {
        $schoolId = (int) $student->school_id;
        if ($student->exit_reason !== 'left_school' || !$student->reactivation_requested_at || $student->reactivation_approved_at) {
            return response()->json(['message' => 'This student does not have a pending left-school reactivation request.'], 422);
        }

        $user = User::query()->where('id', $student->user_id)->where('school_id', $schoolId)->first();
        if (!$user) {
            return response()->json(['message' => 'Student user account was not found.'], 404);
        }

        $user->is_active = true;
        $user->save();
        $student->exit_reason = null;
        $student->reactivation_approved_at = now();
        $student->reactivation_approved_by_user_id = (int) $request->user()->id;
        $student->reactivation_requested_at = null;
        $student->reactivation_requested_by_user_id = null;
        $student->save();

        return response()->json(['message' => 'Student reactivation approved and access enabled.']);
    }
    private function validateDeleteCode(Request $request): void
    {
        $payload = $request->validate([
            'delete_code' => 'required|digits:4',
        ]);

        $expectedCode = (string) config('app.super_admin_delete_confirmation_code', '4722');
        if ((string) ($payload['delete_code'] ?? '') !== $expectedCode) {
            abort(response()->json([
                'message' => 'Invalid super admin delete confirmation code.',
                'errors' => [
                    'delete_code' => ['The delete confirmation code is incorrect.'],
                ],
            ], 422));
        }
    }

    private function normalizeLevelValue(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? '';
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'creche')) {
            return 'creche';
        }
        if (str_starts_with($normalized, 'pre_nursery') || str_starts_with($normalized, 'prenursery')) {
            return 'pre_nursery';
        }
        if (str_starts_with($normalized, 'nursery')) {
            return 'nursery';
        }
        if (str_starts_with($normalized, 'primary')) {
            return 'primary';
        }
        if (str_starts_with($normalized, 'secondary')) {
            return 'secondary';
        }

        return $normalized;
    }
}
