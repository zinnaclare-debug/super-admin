<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        $photoPath = $student?->photo_path ?? $staff?->photo_path;

        return response()->json([
            'data' => [
                'id' => $user->id,
                'role' => $user->role,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'education_level' => $staff?->education_level,
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

            'education_level' => ['nullable', Rule::in(['nursery', 'primary', 'secondary'])],
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
        $user->email = $validated['email'] ?? null;
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $dir = "schools/{$user->school_id}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            $filename = $user->username . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
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
            $staff->education_level = $validated['education_level'] ?? $staff->education_level;
            if ($photoPath) {
                $staff->photo_path = $photoPath;
            }
            $staff->save();
        }

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
