<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\ClassTemplateSchema;
use App\Support\DepartmentTemplateSync;
use App\Support\UserCredentialStore;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    // GET /api/school-admin/users?status=active|inactive&role=staff|student
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $status = $request->query('status', 'active'); // active or inactive
        $role = $request->query('role'); // staff or student (optional)
        $isActive = $status === 'active';

        $query = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('role', ['student', 'staff']) // don't include school_admin
            ->where('is_active', $isActive);

        // Filter by role if provided
        if ($role && in_array($role, ['student', 'staff'])) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('name')->get(['id', 'name', 'email', 'username', 'role', 'is_active']);

        return response()->json(['data' => $users]);
    }

    // GET /api/school-admin/users/{user}
    public function show(Request $request, User $user)
    {
        // multi-tenant safety
        if ($user->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json([
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'school_id'])
        ]);
    }

    // GET /api/school-admin/users/{user}/edit-data
    public function editData(Request $request, User $user)
    {
        if ($user->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!in_array($user->role, ['student', 'staff'], true)) {
            return response()->json(['message' => 'Only student/staff can be edited here'], 422);
        }

        $student = null;
        $staff = null;
        $guardian = null;

        if ($user->role === 'student') {
            $student = Student::where('school_id', $user->school_id)->where('user_id', $user->id)->first();
            $guardian = Guardian::where('school_id', $user->school_id)->where('user_id', $user->id)->first();
        } else {
            $staff = Staff::where('school_id', $user->school_id)->where('user_id', $user->id)->first();
        }

        $placement = ['class_id' => null, 'department_id' => null, 'class_name' => null, 'department_name' => null];
        if ($user->role === 'student' && $student) {
            $placement = $this->resolveStudentCurrentPlacement((int) $user->school_id, (int) $student->id);
        }

        $photoPath = $student?->photo_path ?? $staff?->photo_path ?? $user->photo_path;

        return response()->json([
            'data' => [
                'id' => $user->id,
                'role' => $user->role,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'education_level' => $student?->education_level ?? $staff?->education_level,
                'sex' => $student?->sex ?? $staff?->sex,
                'religion' => $student?->religion,
                'dob' => $student?->dob ?? $staff?->dob,
                'address' => $student?->address ?? $staff?->address,
                'staff_position' => $staff?->position,
                'photo_url' => $this->storageUrl($photoPath),
                'guardian_name' => $guardian?->name,
                'guardian_email' => $guardian?->email,
                'guardian_mobile' => $guardian?->mobile,
                'guardian_location' => $guardian?->location,
                'guardian_state_of_origin' => $guardian?->state_of_origin,
                'guardian_occupation' => $guardian?->occupation,
                'guardian_relationship' => $guardian?->relationship,
                'class_id' => $placement['class_id'],
                'department_id' => $placement['department_id'],
                'class_name' => $placement['class_name'],
                'department_name' => $placement['department_name'],
            ],
        ]);
    }

    // POST /api/school-admin/users/{user}/update
    public function update(Request $request, User $user)
    {
        if ($user->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!in_array($user->role, ['student', 'staff'], true)) {
            return response()->json(['message' => 'Only student/staff can be edited here'], 422);
        }

        $emailRule = $user->role === 'staff'
            ? ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)]
            : ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRule,
            'password' => ['nullable', 'string', 'min:6'],

            'education_level' => ['nullable', 'string', 'max:60'],
            'sex' => ['nullable', 'string', 'max:10'],
            'religion' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:1000'],
            'staff_position' => ['nullable', 'string', 'max:255'],

            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_mobile' => ['nullable', 'string', 'max:255'],
            'guardian_location' => ['nullable', 'string', 'max:255'],
            'guardian_state_of_origin' => ['nullable', 'string', 'max:255'],
            'guardian_occupation' => ['nullable', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', 'string', 'max:255'],

            'remove_photo' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($user->role === 'student') {
            $rules['class_id'] = ['required', 'integer', 'exists:classes,id'];
            $rules['department_id'] = ['nullable', 'integer', 'exists:class_departments,id'];
        } else {
            $rules['class_id'] = ['nullable', 'integer'];
            $rules['department_id'] = ['nullable', 'integer'];
        }

        $validated = $request->validate($rules);

        $user->name = $validated['name'];
        if ($user->role === 'staff') {
            $user->email = $validated['email'];
        } elseif (array_key_exists('email', $validated) && filled($validated['email'])) {
            // For students, keep existing email unless a new non-empty value is provided.
            $user->email = $validated['email'];
        }
        $educationLevel = $this->normalizeEducationLevel($validated['education_level'] ?? null);
        if ($educationLevel !== null && !$this->isValidEducationLevel((int) $user->school_id, $educationLevel)) {
            return response()->json([
                'message' => 'Invalid education level selected.',
            ], 422);
        }
        $plainPassword = !empty($validated['password']) ? (string) $validated['password'] : null;
        if ($plainPassword !== null) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        $existingStudentPhotoPath = Student::where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->value('photo_path');
        $existingStaffPhotoPath = Staff::where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->value('photo_path');

        $currentPhotoPaths = collect([
            $user->photo_path,
            $existingStudentPhotoPath,
            $existingStaffPhotoPath,
        ])->filter(fn ($path) => filled($path))->unique()->values();

        $photoPath = null;
        $removePhoto = filter_var($validated['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($request->hasFile('photo')) {
            $dir = "schools/{$user->school_id}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            // Use a versioned filename to avoid stale browser-cached images after edit.
            $filename = $user->username . '-' . now()->timestamp . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
            $user->photo_path = $photoPath;
            $user->save();
        } elseif ($removePhoto) {
            $user->photo_path = null;
            $user->save();
        }

        if ($user->role === 'student') {
            $student = Student::firstOrCreate(
                ['school_id' => $user->school_id, 'user_id' => $user->id],
                ['school_id' => $user->school_id, 'user_id' => $user->id]
            );

            $requestedClassId = (int) ($validated['class_id'] ?? 0);
            $requestedDepartmentId = $request->filled('department_id')
                ? (int) $validated['department_id']
                : null;

            $placement = $this->resolveStudentPlacementForUpdate(
                (int) $user->school_id,
                $requestedClassId,
                $requestedDepartmentId,
                $educationLevel
            );
            $classLevel = strtolower(trim((string) $placement['class']->level));

            $student->sex = $validated['sex'] ?? $student->sex;
            $student->religion = $validated['religion'] ?? $student->religion;
            $student->dob = $validated['dob'] ?? $student->dob;
            $student->address = $validated['address'] ?? $student->address;
            if (Schema::hasColumn('students', 'education_level')) {
                $student->education_level = $educationLevel ?? $classLevel;
            }
            if ($photoPath) {
                $student->photo_path = $photoPath;
            } elseif ($removePhoto) {
                $student->photo_path = null;
            }
            $student->save();

            $this->reassignStudentInClassSession(
                (int) $user->school_id,
                $student,
                $placement['class'],
                $placement['session_term_ids'],
                $placement['department_id']
            );

            $guardianPayload = [
                'name' => $validated['guardian_name'] ?? null,
                'email' => $validated['guardian_email'] ?? null,
                'mobile' => $validated['guardian_mobile'] ?? null,
                'location' => $validated['guardian_location'] ?? null,
                'state_of_origin' => $validated['guardian_state_of_origin'] ?? null,
                'occupation' => $validated['guardian_occupation'] ?? null,
                'relationship' => $validated['guardian_relationship'] ?? null,
            ];

            $hasGuardianData = collect($guardianPayload)->filter(fn ($v) => filled($v))->isNotEmpty();

            if ($hasGuardianData) {
                Guardian::updateOrCreate(
                    ['school_id' => $user->school_id, 'user_id' => $user->id],
                    $guardianPayload
                );
            }
        } else {
            $staff = Staff::firstOrCreate(
                ['school_id' => $user->school_id, 'user_id' => $user->id],
                ['school_id' => $user->school_id, 'user_id' => $user->id]
            );

            $staff->sex = $validated['sex'] ?? $staff->sex;
            $staff->dob = $validated['dob'] ?? $staff->dob;
            $staff->address = $validated['address'] ?? $staff->address;
            $staff->position = $validated['staff_position'] ?? $staff->position;
            $staff->education_level = $educationLevel ?? $staff->education_level;
            if ($photoPath) {
                $staff->photo_path = $photoPath;
            } elseif ($removePhoto) {
                $staff->photo_path = null;
            }
            $staff->save();
        }

        $updatedStudentPhotoPath = Student::where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->value('photo_path');
        $updatedStaffPhotoPath = Staff::where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->value('photo_path');

        $activePhotoPaths = collect([
            $user->photo_path,
            $updatedStudentPhotoPath,
            $updatedStaffPhotoPath,
        ])->filter(fn ($path) => filled($path))->unique()->values();

        $pathsToDelete = $currentPhotoPaths->diff($activePhotoPaths)->values();
        foreach ($pathsToDelete as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        UserCredentialStore::sync(
            $user,
            $plainPassword,
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    // POST /api/school-admin/users/{user}/reset-password
    public function resetPassword(Request $request, User $user)
    {
        $schoolId = (int) $request->user()->school_id;

        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!in_array($user->role, ['student', 'staff'], true)) {
            return response()->json(['message' => 'Only student/staff passwords can be reset here'], 422);
        }

        $payload = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->password = Hash::make($payload['password']);
        $user->save();

        UserCredentialStore::sync(
            $user,
            (string) $payload['password'],
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Password reset successfully',
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    // PATCH /api/school-admin/users/{user}/toggle
    public function toggle(Request $request, User $user)
    {
        if ($user->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // optional: prevent disabling self / prevent disabling super admins
        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return response()->json([
            'message' => 'User status updated',
            'data' => $user->only(['id', 'is_active'])
        ]);
    }

    // DELETE /api/school-admin/users/{user}
    public function destroy(Request $request, User $user)
    {
        $schoolId = (int) $request->user()->school_id;

        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!in_array($user->role, ['student', 'staff'], true)) {
            return response()->json(['message' => 'Only student/staff can be deleted here'], 422);
        }

        $photoPaths = $this->collectUserPhotoPaths($schoolId, $user);

        $userName = $user->name;

        try {
            $user->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Unable to delete this user because related records are protected.',
            ], 409);
        }

        $this->deleteStoredPhotoPaths($photoPaths);

        return response()->json([
            'message' => "User {$userName} deleted successfully",
            'data' => ['id' => $user->id],
        ]);
    }

    // DELETE /api/school-admin/users/bulk-delete
    public function bulkDestroy(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;

        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $requestedIds = collect($payload['ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $requestedIds)
            ->whereIn('role', ['student', 'staff'])
            ->get();

        $matchedIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $missingIds = array_values(array_diff($requestedIds->all(), $matchedIds));

        $deletedIds = [];
        $failed = [];

        foreach ($users as $user) {
            $photoPaths = $this->collectUserPhotoPaths($schoolId, $user);
            try {
                $user->delete();
            } catch (QueryException $e) {
                $failed[] = [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'reason' => 'protected',
                ];
                continue;
            }

            $deletedIds[] = (int) $user->id;
            $this->deleteStoredPhotoPaths($photoPaths);
        }

        $deletedCount = count($deletedIds);
        $failedCount = count($failed);
        $missingCount = count($missingIds);

        $messageParts = [
            $deletedCount === 1
                ? '1 user deleted successfully'
                : "{$deletedCount} users deleted successfully",
        ];
        if ($failedCount > 0) {
            $messageParts[] = "{$failedCount} protected user(s) could not be deleted";
        }
        if ($missingCount > 0) {
            $messageParts[] = "{$missingCount} user(s) not found or not allowed";
        }

        return response()->json([
            'message' => implode('. ', $messageParts) . '.',
            'data' => [
                'requested' => $requestedIds->count(),
                'matched' => count($matchedIds),
                'deleted_count' => $deletedCount,
                'deleted_ids' => $deletedIds,
                'failed' => $failed,
                'missing_ids' => $missingIds,
            ],
        ]);
    }

    private function resolveStudentCurrentPlacement(int $schoolId, int $studentId): array
    {
        $placement = [
            'class_id' => null,
            'department_id' => null,
            'class_name' => null,
            'department_name' => null,
        ];

        $currentSession = $this->resolveCurrentSession($schoolId);
        $classId = null;
        $departmentId = null;

        if ($currentSession) {
            $termId = $this->resolveCurrentSessionTermId($schoolId, (int) $currentSession->id);
            if ($termId) {
                $enrollmentQuery = Enrollment::query()
                    ->where('student_id', $studentId)
                    ->where('term_id', $termId)
                    ->orderByDesc('id');
                if (Schema::hasColumn('enrollments', 'school_id')) {
                    $enrollmentQuery->where('school_id', $schoolId);
                }
                $enrollment = $enrollmentQuery->first(['class_id', 'department_id']);
                if ($enrollment) {
                    $classId = (int) $enrollment->class_id;
                    $departmentId = $enrollment->department_id ? (int) $enrollment->department_id : null;
                }
            }

            if (!$classId) {
                $classId = DB::table('class_students')
                    ->where('school_id', $schoolId)
                    ->where('student_id', $studentId)
                    ->where('academic_session_id', (int) $currentSession->id)
                    ->orderByDesc('id')
                    ->value('class_id');
                $classId = $classId ? (int) $classId : null;
            }
        }

        if (!$classId) {
            $enrollmentQuery = Enrollment::query()
                ->where('student_id', $studentId)
                ->orderByDesc('id');
            if (Schema::hasColumn('enrollments', 'school_id')) {
                $enrollmentQuery->where('school_id', $schoolId);
            }
            $enrollment = $enrollmentQuery->first(['class_id', 'department_id']);
            if ($enrollment) {
                $classId = (int) $enrollment->class_id;
                $departmentId = $enrollment->department_id ? (int) $enrollment->department_id : null;
            }
        }

        if (!$classId) {
            return $placement;
        }

        $class = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('id', $classId)
            ->first(['id', 'name']);

        if (!$class) {
            return $placement;
        }

        $placement['class_id'] = (int) $class->id;
        $placement['class_name'] = (string) $class->name;
        $placement['department_id'] = $departmentId;

        if ($departmentId) {
            $department = ClassDepartment::query()
                ->where('school_id', $schoolId)
                ->where('id', $departmentId)
                ->first(['name']);
            $placement['department_name'] = $department?->name;
        }

        return $placement;
    }

    private function resolveStudentPlacementForUpdate(
        int $schoolId,
        int $classId,
        ?int $departmentId,
        ?string $educationLevel
    ): array {
        $class = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('id', $classId)
            ->first();

        if (!$class) {
            throw ValidationException::withMessages([
                'class_id' => ['Invalid class selected.'],
            ]);
        }

        $currentSession = $this->resolveCurrentSession($schoolId);
        if ($currentSession && (int) $class->academic_session_id !== (int) $currentSession->id) {
            throw ValidationException::withMessages([
                'class_id' => ['Selected class must belong to current academic session.'],
            ]);
        }

        $classLevel = strtolower(trim((string) $class->level));
        if ($educationLevel !== null && $educationLevel !== $classLevel) {
            throw ValidationException::withMessages([
                'class_id' => ['Selected class does not match the chosen education level.'],
            ]);
        }

        $sessionTermIds = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($sessionTermIds)) {
            throw ValidationException::withMessages([
                'class_id' => ['No terms found for selected class session.'],
            ]);
        }

        $classDepartments = $this->loadTemplateScopedClassDepartments($schoolId, $class);

        $resolvedDepartmentId = null;
        if ($classDepartments->isNotEmpty()) {
            if (!$departmentId) {
                throw ValidationException::withMessages([
                    'department_id' => ['Select department for the selected class.'],
                ]);
            }

            $departmentExists = $classDepartments
                ->pluck('id')
                ->contains((int) $departmentId);

            if (!$departmentExists) {
                throw ValidationException::withMessages([
                    'department_id' => ['Invalid department selected for this class.'],
                ]);
            }

            $resolvedDepartmentId = (int) $departmentId;
        } elseif ($departmentId) {
            throw ValidationException::withMessages([
                'department_id' => ['Selected class has no departments configured.'],
            ]);
        }

        return [
            'class' => $class,
            'department_id' => $resolvedDepartmentId,
            'session_term_ids' => $sessionTermIds,
        ];
    }

    private function reassignStudentInClassSession(
        int $schoolId,
        Student $student,
        SchoolClass $class,
        array $sessionTermIds,
        ?int $departmentId
    ): void {
        DB::table('class_students')
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('class_id', '!=', $class->id)
            ->delete();

        DB::table('class_students')->updateOrInsert([
            'school_id' => $schoolId,
            'academic_session_id' => $class->academic_session_id,
            'class_id' => $class->id,
            'student_id' => $student->id,
        ], [
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        foreach ($sessionTermIds as $termId) {
            $termEnrollments = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('term_id', (int) $termId)
                ->orderByDesc('id');

            if (Schema::hasColumn('enrollments', 'school_id')) {
                $termEnrollments->where('school_id', $schoolId);
            }

            $rows = $termEnrollments->get();
            if ($rows->isEmpty()) {
                $payload = [
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'term_id' => (int) $termId,
                    'department_id' => $departmentId,
                ];
                if (Schema::hasColumn('enrollments', 'school_id')) {
                    $payload['school_id'] = $schoolId;
                }
                Enrollment::create($payload);
                continue;
            }

            $keeper = $rows->first();
            $keeper->class_id = $class->id;
            $keeper->department_id = $departmentId;
            $keeper->save();

            $duplicateIds = $rows->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (!empty($duplicateIds)) {
                Enrollment::query()->whereIn('id', $duplicateIds)->delete();
            }
        }
    }

    private function resolveCurrentSession(int $schoolId): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();
    }

    private function resolveCurrentSessionTermId(int $schoolId, int $academicSessionId): ?int
    {
        $termQuery = Term::query()
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

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);
        if (Storage::disk('public')->exists($path)) {
            $version = Storage::disk('public')->lastModified($path);
            $relativeOrAbsolute .= (str_contains($relativeOrAbsolute, '?') ? '&' : '?') . 'v=' . $version;
        }

        return str_starts_with($relativeOrAbsolute, 'http://')
            || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
    }

    private function normalizeEducationLevel(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        return $normalized !== '' ? $normalized : null;
    }

    private function isValidEducationLevel(int $schoolId, string $level): bool
    {
        $normalizedLevel = strtolower(trim($level));
        if ($normalizedLevel === '') {
            return false;
        }

        $existsInClasses = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(level) = ?', [$normalizedLevel])
            ->exists();
        if ($existsInClasses) {
            return true;
        }

        $school = School::query()->find($schoolId);
        if (!$school) {
            return false;
        }

        $activeTemplateLevels = ClassTemplateSchema::activeLevelKeys(
            ClassTemplateSchema::normalize($school->class_templates)
        );

        return in_array($normalizedLevel, $activeTemplateLevels, true);
    }

    private function collectUserPhotoPaths(int $schoolId, User $user): array
    {
        return collect([
            $user->photo_path,
            Student::query()
                ->where('school_id', $schoolId)
                ->where('user_id', $user->id)
                ->value('photo_path'),
            Staff::query()
                ->where('school_id', $schoolId)
                ->where('user_id', $user->id)
                ->value('photo_path'),
        ])
            ->filter(fn ($path) => filled($path))
            ->unique()
            ->values()
            ->all();
    }

    private function deleteStoredPhotoPaths(array $photoPaths): void
    {
        foreach ($photoPaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
