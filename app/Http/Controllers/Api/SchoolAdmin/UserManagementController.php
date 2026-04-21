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
use App\Support\SchoolPublicWebsiteData;
use App\Support\UserCredentialStore;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserManagementController extends Controller
{
    // GET /api/school-admin/users?status=active|inactive&role=staff|student
    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['nullable', Rule::in(['student', 'staff'])],
            'level' => ['nullable', 'string', 'max:60'],
            'class' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = (string) ($payload['status'] ?? 'active');
        $role = $payload['role'] ?? null;
        $isActive = $status === 'active';
        $page = max(1, (int) ($payload['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($payload['per_page'] ?? 100)));

        $query = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('role', ['student', 'staff'])
            ->where('is_active', $isActive);

        if ($role && in_array($role, ['student', 'staff'], true)) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('name')->get(['id', 'name', 'email', 'username', 'role', 'is_active']);
        $allRows = $this->buildUserIndexRows($schoolId, $users);
        $rows = $this->filterUserIndexRows(
            $allRows,
            $payload['level'] ?? null,
            $payload['class'] ?? null,
            $payload['department'] ?? null,
            $payload['q'] ?? null
        );

        $levels = collect($allRows)
            ->flatMap(fn (array $row) => array_filter(array_map('trim', array_map('strval', $row['levels'] ?? []))))
            ->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => strtolower($value))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values()
            ->all();

        $classes = collect($allRows)
            ->flatMap(fn (array $row) => array_filter(array_map('trim', array_map('strval', $row['classes'] ?? []))))
            ->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => strtolower($value))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values()
            ->all();

        $departments = collect($allRows)
            ->flatMap(fn (array $row) => array_filter(array_map('trim', array_map('strval', $row['departments'] ?? []))))
            ->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => strtolower($value))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values()
            ->all();

        $paginated = $this->paginateArrayRows($rows, $perPage, $page);

        return response()->json([
            'data' => $paginated['data'],
            'meta' => [
                'levels' => $levels,
                'classes' => $classes,
                'departments' => $departments,
                'selected' => [
                    'status' => $status,
                    'role' => $role,
                    'level' => $payload['level'] ?? null,
                    'class' => $payload['class'] ?? null,
                    'department' => $payload['department'] ?? null,
                    'q' => $payload['q'] ?? null,
                ],
                'current_page' => $paginated['current_page'],
                'last_page' => $paginated['last_page'],
                'per_page' => $paginated['per_page'],
                'total' => $paginated['total'],
            ],
        ]);
    }

    // GET /api/school-admin/users/download/pdf?status=active|inactive&role=staff|student&level=&class=&department=&q=
    public function downloadPdf(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['nullable', Rule::in(['student', 'staff'])],
            'level' => ['nullable', 'string', 'max:60'],
            'class' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $status = (string) ($payload['status'] ?? 'active');
        $role = $payload['role'] ?? null;
        $isActive = $status === 'active';

        $users = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('role', ['student', 'staff'])
            ->where('is_active', $isActive)
            ->when($role, fn ($q) => $q->where('role', $role))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'username', 'role', 'is_active']);

        $rows = $this->buildUserIndexRows($schoolId, $users);
        $rows = $this->filterUserIndexRows(
            $rows,
            $payload['level'] ?? null,
            $payload['class'] ?? null,
            $payload['department'] ?? null,
            $payload['q'] ?? null
        );

        $rows = collect($rows)
            ->values()
            ->map(function (array $row, int $index) {
                $row['sn'] = $index + 1;
                return $row;
            })
            ->all();

        $school = School::query()->find($schoolId);

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.users_list', [
                'school' => $school,
                'rows' => $rows,
                'filters' => [
                    'status' => $status,
                    'role' => $role,
                    'level' => $payload['level'] ?? null,
                    'class' => $payload['class'] ?? null,
                    'department' => $payload['department'] ?? null,
                    'q' => $payload['q'] ?? null,
                ],
                'generatedAt' => now(),
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $fileName = 'users_' . $status . '_' . now()->format('Ymd_His') . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to generate users PDF download.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/school-admin/users/{user}/id-card
    public function downloadIdCard(Request $request, User $user)
    {
        $schoolId = (int) $request->user()->school_id;

        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!in_array($user->role, ['student', 'staff'], true)) {
            return response()->json(['message' => 'Only student and staff ID cards are available here.'], 422);
        }

        $school = School::query()->find($schoolId);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        $student = null;
        $staff = null;
        $placement = [
            'class_id' => null,
            'department_id' => null,
            'class_name' => null,
            'department_name' => null,
        ];
        $staffAssignment = [
            'levels' => [],
            'classes' => [],
            'departments' => [],
        ];

        if ($user->role === 'student') {
            $student = Student::query()
                ->where('school_id', $schoolId)
                ->where('user_id', (int) $user->id)
                ->first();

            if ($student) {
                $placement = $this->resolveStudentCurrentPlacement($schoolId, (int) $student->id);
            }
        } else {
            $staff = Staff::query()
                ->where('school_id', $schoolId)
                ->where('user_id', (int) $user->id)
                ->first();

            $staffAssignment = $this->resolveStaffAssignmentsForUsers($schoolId, [(int) $user->id])[(int) $user->id] ?? $staffAssignment;
        }

        $websiteContent = SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);
        $photoPath = $student?->photo_path ?? $staff?->photo_path ?? $user->photo_path;
        $primaryColor = (string) ($websiteContent['primary_color'] ?? '#0f172a');
        $accentColor = (string) ($websiteContent['accent_color'] ?? '#0f766e');

        $frontRoleLabel = $user->role === 'student' ? 'Student ID Card' : 'Staff ID Card';
        $displayLevel = $user->role === 'student'
            ? $this->normalizeEducationLevel($student?->education_level ?? null)
            : ($this->normalizeEducationLevel($staff?->education_level ?? null) ?: ($staffAssignment['levels'][0] ?? null));
        $displayClass = $user->role === 'student'
            ? ($placement['class_name'] ?? null)
            : ($staffAssignment['classes'][0] ?? null);
        $displayDepartment = $user->role === 'student'
            ? ($placement['department_name'] ?? null)
            : ($staffAssignment['departments'][0] ?? null);
        $displayPosition = $user->role === 'staff' ? ($staff?->position ?: 'Staff Member') : null;

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.user_id_card', [
                'school' => $school,
                'user' => $user,
                'student' => $student,
                'staff' => $staff,
                'roleLabel' => $frontRoleLabel,
                'identityNumber' => $user->username ?: ('ID-' . (int) $user->id),
                'displayLevel' => $displayLevel ? strtoupper(str_replace('_', ' ', (string) $displayLevel)) : null,
                'displayClass' => $displayClass,
                'displayDepartment' => $displayDepartment,
                'displayPosition' => $displayPosition,
                'principalName' => $school->head_of_school_name ?: 'Principal',
                'logoDataUri' => $this->imageDataUri($school->logo_path),
                'userPhotoDataUri' => $this->imageDataUri($photoPath) ?: $this->assetImageDataUri(public_path('defaults/student-photo-placeholder.svg')),
                'primaryColor' => $primaryColor,
                'accentColor' => $accentColor,
                'primarySoft' => $this->blendHexColor($primaryColor, '#ffffff', 0.82),
                'accentSoft' => $this->blendHexColor($accentColor, '#ffffff', 0.84),
                'schoolMotto' => (string) ($websiteContent['motto'] ?? ''),
                'contactAddress' => (string) ($websiteContent['address'] ?? $school->location ?? ''),
                'contactEmail' => (string) ($websiteContent['contact_email'] ?? $school->contact_email ?? $school->email ?? ''),
                'contactPhone' => (string) ($websiteContent['contact_phone'] ?? $school->contact_phone ?? ''),
                'websiteUrl' => $this->resolveSchoolWebsiteUrl($request, $school),
            ])->render();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($user->name ?: $user->username ?: $user->id));
            $filename = strtolower($user->role) . '_id_card_' . trim((string) $safeName, '_') . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to generate ID card PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
        $credential = $user->loginCredential()->first();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'role' => $user->role,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'password' => UserCredentialStore::reveal($credential?->password_encrypted) ?? '',
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
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:150'],
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

    private function buildUserIndexRows(int $schoolId, $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $studentsByUserId = Student::query()
            ->where('school_id', $schoolId)
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'education_level'])
            ->keyBy('user_id');

        $staffByUserId = Staff::query()
            ->where('school_id', $schoolId)
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'education_level'])
            ->keyBy('user_id');

        $staffAssignments = $this->resolveStaffAssignmentsForUsers($schoolId, $userIds);

        return $users->map(function ($user) use ($schoolId, $studentsByUserId, $staffByUserId, $staffAssignments) {
            $row = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) ($user->email ?? ''),
                'username' => (string) ($user->username ?? ''),
                'role' => (string) $user->role,
                'is_active' => (bool) $user->is_active,
                'status' => $user->is_active ? 'active' : 'inactive',
                'education_level' => null,
                'class_name' => null,
                'department_name' => null,
                'levels' => [],
                'classes' => [],
                'departments' => [],
            ];

            if ($user->role === 'student') {
                $student = $studentsByUserId->get($user->id);
                if ($student) {
                    $educationLevel = $this->normalizeEducationLevel($student->education_level ?? null);
                    $placement = $this->resolveStudentCurrentPlacement($schoolId, (int) $student->id);

                    $className = trim((string) ($placement['class_name'] ?? ''));
                    $departmentName = trim((string) ($placement['department_name'] ?? ''));

                    $row['education_level'] = $educationLevel;
                    $row['class_name'] = $className !== '' ? $className : null;
                    $row['department_name'] = $departmentName !== '' ? $departmentName : null;
                    $row['levels'] = $educationLevel ? [$educationLevel] : [];
                    $row['classes'] = $className !== '' ? [$className] : [];
                    $row['departments'] = $departmentName !== '' ? [$departmentName] : [];
                }

                return $row;
            }

            if ($user->role === 'staff') {
                $staff = $staffByUserId->get($user->id);
                $assignment = $staffAssignments[(int) $user->id] ?? [
                    'levels' => [],
                    'classes' => [],
                    'departments' => [],
                ];

                $educationLevel = $this->normalizeEducationLevel($staff?->education_level ?? null);
                $levels = collect($assignment['levels'] ?? [])
                    ->map(fn ($level) => $this->normalizeEducationLevel((string) $level))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                if ($educationLevel && !in_array($educationLevel, $levels, true)) {
                    array_unshift($levels, $educationLevel);
                }

                $classes = collect($assignment['classes'] ?? [])
                    ->map(fn ($name) => trim((string) $name))
                    ->filter(fn ($name) => $name !== '')
                    ->unique(fn ($name) => strtolower($name))
                    ->values()
                    ->all();

                $departments = collect($assignment['departments'] ?? [])
                    ->map(fn ($name) => trim((string) $name))
                    ->filter(fn ($name) => $name !== '')
                    ->unique(fn ($name) => strtolower($name))
                    ->values()
                    ->all();

                $row['education_level'] = $educationLevel ?: ($levels[0] ?? null);
                $row['class_name'] = $classes[0] ?? null;
                $row['department_name'] = $departments[0] ?? null;
                $row['levels'] = $levels;
                $row['classes'] = $classes;
                $row['departments'] = $departments;
            }

            return $row;
        })->values()->all();
    }

    private function filterUserIndexRows(
        array $rows,
        ?string $level = null,
        ?string $className = null,
        ?string $department = null,
        ?string $search = null
    ): array {
        $levelNeedle = strtolower(trim((string) ($level ?? '')));
        $classNeedle = strtolower(trim((string) ($className ?? '')));
        $departmentNeedle = strtolower(trim((string) ($department ?? '')));
        $searchNeedle = strtolower(trim((string) ($search ?? '')));

        return collect($rows)
            ->filter(function (array $row) use ($levelNeedle, $classNeedle, $departmentNeedle, $searchNeedle) {
                $levels = collect($row['levels'] ?? [])
                    ->push($row['education_level'] ?? null)
                    ->map(fn ($v) => strtolower(trim((string) $v)))
                    ->filter(fn ($v) => $v !== '')
                    ->unique()
                    ->values()
                    ->all();
                $classes = collect($row['classes'] ?? [])
                    ->push($row['class_name'] ?? null)
                    ->map(fn ($v) => strtolower(trim((string) $v)))
                    ->filter(fn ($v) => $v !== '')
                    ->unique()
                    ->values()
                    ->all();
                $departments = collect($row['departments'] ?? [])
                    ->push($row['department_name'] ?? null)
                    ->map(fn ($v) => strtolower(trim((string) $v)))
                    ->filter(fn ($v) => $v !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($levelNeedle !== '' && !in_array($levelNeedle, $levels, true)) {
                    return false;
                }
                if ($classNeedle !== '' && !in_array($classNeedle, $classes, true)) {
                    return false;
                }
                if ($departmentNeedle !== '' && !in_array($departmentNeedle, $departments, true)) {
                    return false;
                }

                if ($searchNeedle !== '') {
                    $name = strtolower((string) ($row['name'] ?? ''));
                    $email = strtolower((string) ($row['email'] ?? ''));
                    $username = strtolower((string) ($row['username'] ?? ''));
                    if (
                        !str_contains($name, $searchNeedle)
                        && !str_contains($email, $searchNeedle)
                        && !str_contains($username, $searchNeedle)
                    ) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
    }

    private function paginateArrayRows(array $rows, int $perPage, int $page): array
    {
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;
        $items = array_slice(array_values($rows), $offset, $perPage);

        $items = array_map(function (array $row, int $index) use ($offset) {
            $row['sn'] = $offset + $index + 1;
            return $row;
        }, $items, array_keys($items));

        return [
            'data' => array_values($items),
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    private function resolveStaffAssignmentsForUsers(int $schoolId, array $staffUserIds): array
    {
        $userIds = collect($staffUserIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($userIds)) {
            return [];
        }

        $assignments = [];
        foreach ($userIds as $userId) {
            $assignments[$userId] = [
                'levels' => [],
                'classes' => [],
                'departments' => [],
            ];
        }

        $currentSession = $this->resolveCurrentSession($schoolId);

        $classTeacherQuery = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->whereIn('class_teacher_user_id', $userIds);
        if ($currentSession) {
            $classTeacherQuery->where('academic_session_id', (int) $currentSession->id);
        }

        $classTeacherRows = $classTeacherQuery->get(['class_teacher_user_id', 'name', 'level']);
        foreach ($classTeacherRows as $row) {
            $userId = (int) ($row->class_teacher_user_id ?? 0);
            if ($userId <= 0 || !array_key_exists($userId, $assignments)) {
                continue;
            }
            $level = $this->normalizeEducationLevel((string) ($row->level ?? ''));
            $className = trim((string) ($row->name ?? ''));
            if ($level && !in_array($level, $assignments[$userId]['levels'], true)) {
                $assignments[$userId]['levels'][] = $level;
            }
            if ($className !== '' && !in_array($className, $assignments[$userId]['classes'], true)) {
                $assignments[$userId]['classes'][] = $className;
            }
        }

        if (Schema::hasTable('class_departments') && Schema::hasColumn('class_departments', 'class_teacher_user_id')) {
            $departmentTeacherQuery = DB::table('class_departments')
                ->join('classes', 'classes.id', '=', 'class_departments.class_id')
                ->where('classes.school_id', $schoolId)
                ->whereIn('class_departments.class_teacher_user_id', $userIds)
                ->whereNotNull('class_departments.class_teacher_user_id')
                ->select([
                    'class_departments.class_teacher_user_id',
                    'class_departments.name as department_name',
                    'classes.name as class_name',
                    'classes.level as class_level',
                ]);
            if ($currentSession) {
                $departmentTeacherQuery->where('classes.academic_session_id', (int) $currentSession->id);
            }

            $departmentTeacherRows = $departmentTeacherQuery->get();
            foreach ($departmentTeacherRows as $row) {
                $userId = (int) ($row->class_teacher_user_id ?? 0);
                if ($userId <= 0 || !array_key_exists($userId, $assignments)) {
                    continue;
                }

                $level = $this->normalizeEducationLevel((string) ($row->class_level ?? ''));
                $className = trim((string) ($row->class_name ?? ''));
                $departmentName = trim((string) ($row->department_name ?? ''));

                if ($level && !in_array($level, $assignments[$userId]['levels'], true)) {
                    $assignments[$userId]['levels'][] = $level;
                }
                if ($className !== '' && !in_array($className, $assignments[$userId]['classes'], true)) {
                    $assignments[$userId]['classes'][] = $className;
                }
                if ($departmentName !== '' && !in_array($departmentName, $assignments[$userId]['departments'], true)) {
                    $assignments[$userId]['departments'][] = $departmentName;
                }
            }
        }

        return $assignments;
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

    private function imageDataUri(?string $storagePath): ?string
    {
        if (!$storagePath || !Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($storagePath);
        return $this->assetImageDataUri($fullPath);
    }

    private function assetImageDataUri(?string $absolutePath): ?string
    {
        if (!$absolutePath || !is_file($absolutePath)) {
            return null;
        }

        $mime = @mime_content_type($absolutePath) ?: 'image/png';
        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private function resolveSchoolWebsiteUrl(Request $request, School $school): string
    {
        $subdomain = trim((string) ($school->subdomain ?? ''));
        if ($subdomain !== '') {
            $candidate = preg_match('#^https?://#i', $subdomain) ? $subdomain : 'https://' . $subdomain;
            return $candidate;
        }

        $origin = trim((string) $request->headers->get('origin', ''));
        if ($origin !== '') {
            return rtrim($origin, '/');
        }

        $frontendUrl = trim((string) env('FRONTEND_URL', ''));
        if ($frontendUrl !== '') {
            return rtrim($frontendUrl, '/');
        }

        return rtrim((string) $request->getSchemeAndHttpHost(), '/');
    }

    private function blendHexColor(string $baseHex, string $targetHex, float $targetWeight): string
    {
        $base = $this->hexToRgb($baseHex);
        $target = $this->hexToRgb($targetHex);
        $weight = max(0, min(1, $targetWeight));

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($base['r'] * (1 - $weight)) + ($target['r'] * $weight)),
            (int) round(($base['g'] * (1 - $weight)) + ($target['g'] * $weight)),
            (int) round(($base['b'] * (1 - $weight)) + ($target['b'] * $weight))
        );
    }

    private function hexToRgb(string $hex): array
    {
        $normalized = ltrim(trim($hex), '#');
        if (strlen($normalized) === 3) {
            $normalized = preg_replace('/(.)/', '$1$1', $normalized) ?: '000000';
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            $normalized = '000000';
        }

        return [
            'r' => hexdec(substr($normalized, 0, 2)),
            'g' => hexdec(substr($normalized, 2, 2)),
            'b' => hexdec(substr($normalized, 4, 2)),
        ];
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





