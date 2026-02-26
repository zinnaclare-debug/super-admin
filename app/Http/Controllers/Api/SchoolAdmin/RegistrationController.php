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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RegistrationController extends Controller
{
    public function preview(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = (int) $school->id;

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'password' => 'required|min:6',
            'education_level' => 'nullable|string|max:60',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
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
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            return response()->json(['message' => 'Invalid education level selected.'], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $dir = "schools/{$schoolId}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            $filename = $request->username . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
        }

        $user = User::create([
            'school_id' => $schoolId,
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'photo_path' => $photoPath,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        UserCredentialStore::sync(
            $user,
            (string) $request->password,
            (int) $request->user()->id
        );

        if ($request->role === 'student') {
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
            Student::create($studentPayload);
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

        if ($request->input('guardian_name')) {
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

        return response()->json([
            'message' => 'User registered successfully',
            'username' => $request->username,
            'photo_url' => $this->storageUrl($photoPath),
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
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            return response()->json(['message' => 'Invalid education level selected.'], 422);
        }

        $username = $this->generateUsername($school, (string) $request->name);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $dir = "schools/{$schoolId}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            $filename = $username . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
        }

        $user = User::create([
            'school_id' => $schoolId,
            'name' => $request->name,
            'username' => $username,
            'email' => $request->email,
            'photo_path' => $photoPath,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        UserCredentialStore::sync(
            $user,
            (string) $request->password,
            (int) $request->user()->id
        );

        if ($request->role === 'student') {
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
            Student::create($studentPayload);
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

        if ($request->input('guardian_name')) {
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

        return response()->json([
            'message' => 'Registration successful',
            'username' => $username,
            'photo_url' => $this->storageUrl($photoPath),
        ], 201);
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
