<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\LevelDepartment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\ClassTemplateSchema;
use App\Support\UserCredentialStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function enrollmentOptions(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;

        $payload = $request->validate([
            'education_level' => 'nullable|string|max:60',
        ]);

        $educationLevel = $this->normalizeEducationLevel($payload['education_level'] ?? null);
        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            return response()->json(['message' => 'Invalid education level selected.'], 422);
        }

        $session = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json([
                'data' => [
                    'current_session' => null,
                    'current_term' => null,
                    'classes' => [],
                ],
                'message' => 'No current academic session',
            ]);
        }

        $termQuery = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id);

        $currentTerm = null;
        if (Schema::hasColumn('terms', 'is_current')) {
            $currentTerm = (clone $termQuery)->where('is_current', true)->first();
        }
        if (!$currentTerm) {
            $currentTerm = (clone $termQuery)->orderBy('id')->first();
        }

        $classesQuery = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('level')
            ->orderBy('name');

        if ($educationLevel !== null) {
            $classesQuery->whereRaw('LOWER(level) = ?', [$educationLevel]);
        }

        $classes = $classesQuery->get(['id', 'name', 'level', 'academic_session_id']);

        foreach ($classes as $class) {
            $this->syncClassDepartmentsFromLevel($schoolId, $class);
        }

        $classIds = $classes->pluck('id')->all();
        $departmentsByClass = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->when(!empty($classIds), function ($q) use ($classIds) {
                $q->whereIn('class_id', $classIds);
            })
            ->orderBy('name')
            ->get(['id', 'class_id', 'name'])
            ->groupBy('class_id');

        return response()->json([
            'data' => [
                'current_session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                    'status' => $session->status,
                ],
                'current_term' => $currentTerm ? [
                    'id' => (int) $currentTerm->id,
                    'name' => $currentTerm->name,
                ] : null,
                'classes' => $classes->map(function ($class) use ($departmentsByClass) {
                    return [
                        'id' => (int) $class->id,
                        'name' => $class->name,
                        'level' => strtolower(trim((string) $class->level)),
                        'departments' => collect($departmentsByClass->get($class->id, collect()))
                            ->map(fn ($department) => [
                                'id' => (int) $department->id,
                                'name' => $department->name,
                            ])
                            ->values()
                            ->all(),
                    ];
                })->values()->all(),
            ],
        ]);
    }

    public function preview(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = (int) $school->id;

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'password' => 'required|min:6',
            'education_level' => 'nullable|string|max:60',
            'class_id' => 'required_if:role,student|integer|exists:classes,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        if ($request->input('role') === 'student') {
            $placement = $this->resolveStudentPlacement(
                $schoolId,
                (int) $request->input('class_id'),
                $request->filled('department_id') ? (int) $request->input('department_id') : null,
                $educationLevel
            );
            if ($educationLevel === null) {
                $educationLevel = strtolower(trim((string) $placement['class']->level));
            }
        }

        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            return response()->json(['message' => 'Invalid education level selected.'], 422);
        }

        $username = $this->generateUsername($school, (string) $request->name);

        return response()->json([
            'username' => $username,
            'message' => 'Username generated successfully',
        ]);
    }

    public function confirm(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = (int) $school->id;

        $emailRule = $request->input('role') === 'staff'
            ? 'required|string|email|unique:users,email'
            : 'nullable|string|email|unique:users,email';

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'email' => $emailRule,
            'password' => 'required|min:6',
            'username' => 'required|string|unique:users,username',
            'education_level' => 'nullable|string|max:60',
            'class_id' => 'required_if:role,student|integer|exists:classes,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        $result = $this->createRegisteredUser($request, $schoolId, (string) $request->input('username'), $educationLevel);

        return response()->json([
            'message' => 'User registered successfully',
            'username' => $result['username'],
            'photo_url' => $this->storageUrl($result['photo_path']),
        ], 201);
    }

    public function register(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = (int) $school->id;

        $emailRule = $request->input('role') === 'staff'
            ? 'required|string|email|unique:users,email'
            : 'nullable|string|email|unique:users,email';

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'password' => 'required|min:6',
            'email' => $emailRule,
            'education_level' => 'nullable|string|max:60',
            'class_id' => 'required_if:role,student|integer|exists:classes,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $username = $this->generateUsername($school, (string) $request->name);
        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        $result = $this->createRegisteredUser($request, $schoolId, $username, $educationLevel);

        return response()->json([
            'message' => 'Registration successful',
            'username' => $result['username'],
            'photo_url' => $this->storageUrl($result['photo_path']),
        ], 201);
    }

    private function createRegisteredUser(
        Request $request,
        int $schoolId,
        string $username,
        ?string $educationLevel
    ): array {
        $studentPlacement = null;
        if ($request->input('role') === 'student') {
            $studentPlacement = $this->resolveStudentPlacement(
                $schoolId,
                (int) $request->input('class_id'),
                $request->filled('department_id') ? (int) $request->input('department_id') : null,
                $educationLevel
            );

            if ($educationLevel === null) {
                $educationLevel = strtolower(trim((string) $studentPlacement['class']->level));
            }
        }

        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            throw ValidationException::withMessages([
                'education_level' => ['Invalid education level selected.'],
            ]);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $dir = "schools/{$schoolId}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            $filename = $username . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
        }

        try {
            DB::transaction(function () use (
                $request,
                $schoolId,
                $username,
                $educationLevel,
                $photoPath,
                $studentPlacement
            ) {
                $user = User::create([
                    'school_id' => $schoolId,
                    'name' => $request->input('name'),
                    'username' => $username,
                    'email' => $request->input('email'),
                    'photo_path' => $photoPath,
                    'password' => Hash::make((string) $request->input('password')),
                    'role' => $request->input('role'),
                ]);

                UserCredentialStore::sync(
                    $user,
                    (string) $request->input('password'),
                    (int) $request->user()->id
                );

                if ($request->input('role') === 'student') {
                    $studentPayload = [
                        'user_id' => $user->id,
                        'school_id' => $schoolId,
                        'sex' => $request->input('sex'),
                        'religion' => $request->input('religion'),
                        'dob' => $request->input('dob'),
                        'address' => $request->input('address'),
                        'photo_path' => $photoPath,
                    ];
                    if (Schema::hasColumn('students', 'education_level')) {
                        $studentPayload['education_level'] = $educationLevel;
                    }

                    $student = Student::create($studentPayload);
                    if ($studentPlacement) {
                        $this->enrollStudentInClassSession(
                            $schoolId,
                            $student,
                            $studentPlacement['class'],
                            $studentPlacement['session_term_ids'],
                            $studentPlacement['department_id']
                        );
                    }
                } else {
                    Staff::create([
                        'user_id' => $user->id,
                        'school_id' => $schoolId,
                        'sex' => $request->input('sex') ?? null,
                        'dob' => $request->input('dob') ?? null,
                        'address' => $request->input('address') ?? null,
                        'position' => $request->input('staff_position') ?? null,
                        'education_level' => $educationLevel,
                        'photo_path' => $photoPath,
                    ]);
                }

                if ($request->filled('guardian_name')) {
                    Guardian::create([
                        'school_id' => $schoolId,
                        'user_id' => $user->id,
                        'name' => $request->input('guardian_name'),
                        'email' => $request->input('guardian_email'),
                        'mobile' => $request->input('guardian_mobile'),
                        'location' => $request->input('guardian_location'),
                        'state_of_origin' => $request->input('guardian_state_of_origin'),
                        'occupation' => $request->input('guardian_occupation'),
                        'relationship' => $request->input('guardian_relationship'),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            throw $exception;
        }

        return [
            'username' => $username,
            'photo_path' => $photoPath,
        ];
    }

    private function resolveStudentPlacement(
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
                'class_id' => ['No terms found for the selected class session.'],
            ]);
        }

        $this->syncClassDepartmentsFromLevel($schoolId, $class);

        $classDepartments = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->orderBy('name')
            ->get(['id']);

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

    private function enrollStudentInClassSession(
        int $schoolId,
        Student $student,
        SchoolClass $class,
        array $sessionTermIds,
        ?int $departmentId
    ): void {
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
            $where = [
                'student_id' => $student->id,
                'class_id' => $class->id,
                'term_id' => (int) $termId,
            ];

            if (Schema::hasColumn('enrollments', 'school_id')) {
                $where['school_id'] = $schoolId;
            }

            $exists = Enrollment::query()->where($where)->exists();
            if ($exists) {
                Enrollment::query()->where($where)->update([
                    'department_id' => $departmentId,
                    'updated_at' => now(),
                ]);
                continue;
            }

            Enrollment::create([
                ...$where,
                'department_id' => $departmentId,
            ]);
        }
    }

    private function syncClassDepartmentsFromLevel(int $schoolId, SchoolClass $class): void
    {
        $levelDepartments = LevelDepartment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('level', $class->level)
            ->get(['name']);

        foreach ($levelDepartments as $department) {
            ClassDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'class_id' => $class->id,
                'name' => $department->name,
            ]);
        }
    }

    private function generateUsername($school, string $fullName): string
    {
        $prefix = strtoupper((string) $school->username_prefix);
        $surname = strtolower(explode(' ', trim($fullName))[0]);

        $lastUser = User::query()
            ->where('school_id', $school->id)
            ->where('username', 'LIKE', "{$prefix}-{$surname}%")
            ->orderByDesc('id')
            ->first();

        if ($lastUser) {
            preg_match('/(\d+)$/', (string) $lastUser->username, $matches);
            $number = isset($matches[1]) ? ((int) $matches[1] + 1) : 1;
        } else {
            $number = 1;
        }

        return "{$prefix}-{$surname}{$number}";
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

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);
        return str_starts_with($relativeOrAbsolute, 'http://')
            || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
    }
}
