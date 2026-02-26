<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\ClassTemplateSchema;
use App\Support\UserCredentialStore;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        $validated = $request->validate([
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

            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

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

        $existingPhotoPath = $user->role === 'student'
            ? Student::where('school_id', $user->school_id)->where('user_id', $user->id)->value('photo_path')
            : Staff::where('school_id', $user->school_id)->where('user_id', $user->id)->value('photo_path');

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $dir = "schools/{$user->school_id}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            // Use a versioned filename to avoid stale browser-cached images after edit.
            $filename = $user->username . '-' . now()->timestamp . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
            $user->photo_path = $photoPath;
            $user->save();
        }

        if ($user->role === 'student') {
            $student = Student::firstOrCreate(
                ['school_id' => $user->school_id, 'user_id' => $user->id],
                ['school_id' => $user->school_id, 'user_id' => $user->id]
            );

            $student->sex = $validated['sex'] ?? $student->sex;
            $student->religion = $validated['religion'] ?? $student->religion;
            $student->dob = $validated['dob'] ?? $student->dob;
            $student->address = $validated['address'] ?? $student->address;
            if (Schema::hasColumn('students', 'education_level')) {
                $student->education_level = $educationLevel ?? $student->education_level;
            }
            if ($photoPath) {
                $student->photo_path = $photoPath;
            }
            $student->save();

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
            }
            $staff->save();
        }

        if ($photoPath && $existingPhotoPath && $existingPhotoPath !== $photoPath && Storage::disk('public')->exists($existingPhotoPath)) {
            Storage::disk('public')->delete($existingPhotoPath);
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

        $photoPaths = collect([
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
            ->values();

        $userName = $user->name;

        try {
            $user->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Unable to delete this user because related records are protected.',
            ], 409);
        }

        foreach ($photoPaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        return response()->json([
            'message' => "User {$userName} deleted successfully",
            'data' => ['id' => $user->id],
        ]);
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
}
