<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use App\Models\User;
use App\Models\Student;
use App\Models\Staff;
use App\Models\Guardian;

class RegistrationController extends Controller
{
    /**
     * STEP 1 - Preview: Validate form and generate username
     * Now supports multipart/form-data (photo can be sent too).
     */
    public function preview(Request $request)
    {
        $school = $request->user()->school;

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'password' => 'required|min:6',

            // important now that you select education_level in UI
            'education_level' => 'nullable|in:nursery,primary,secondary',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',

            // photo can be validated early
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $username = $this->generateUsername($school, $request->name);

        return response()->json([
            'username' => $username,
            'message' => 'Username generated successfully'
        ]);
    }

    /**
     * STEP 2 - Confirm: Create user with form data
     */
    public function confirm(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = $school->id;

        // email rules depend on role
        $emailRule = $request->input('role') === 'staff'
            ? 'required|string|email|unique:users,email'
            : 'nullable|string|email|unique:users,email';

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'email' => $emailRule,
            'password' => 'required|min:6',
            'username' => 'required|string|unique:users,username',

            'education_level' => 'nullable|in:nursery,primary,secondary',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',

            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // ✅ SAVE PHOTO (if any)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $dir = "schools/{$schoolId}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            $filename = $request->username . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
        }

        // -------------------------
        // CREATE USER
        // -------------------------
        $user = User::create([
            'school_id' => $schoolId,
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email, // can be null for student
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        // -------------------------
        // STUDENT / STAFF PROFILE
        // -------------------------
        if ($request->role === 'student') {
            Student::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'sex' => $request->input('sex'),
                'religion' => $request->input('religion'),
                'dob' => $request->input('dob'),
                'address' => $request->input('address'),
                'photo_path' => $photoPath,
            ]);
        } else {
            Staff::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'sex' => $request->input('sex') ?? null,
                'dob' => $request->input('dob') ?? null,
                'address' => $request->input('address') ?? null,
                'position' => $request->input('staff_position') ?? null,
                'education_level' => $request->input('education_level') ?? null,
                'photo_path' => $photoPath,
            ]);
        }

        // -------------------------
        // GUARDIAN (OPTIONAL)
        // -------------------------
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

    /**
     * OPTIONAL: SINGLE-STEP registration
     * Keep it consistent (fix email assignment).
     */
    public function register(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = $school->id;

        $emailRule = $request->input('role') === 'staff'
            ? 'required|string|email|unique:users,email'
            : 'nullable|string|email|unique:users,email';

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'password' => 'required|min:6',

            'email' => $emailRule,
            'education_level' => 'nullable|in:nursery,primary,secondary',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',

            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $username = $this->generateUsername($school, $request->name);

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
            'email' => $request->email, // ✅ fixed
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        if ($request->role === 'student') {
            Student::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'sex' => $request->input('sex'),
                'religion' => $request->input('religion'),
                'dob' => $request->input('dob'),
                'address' => $request->input('address'),
                'photo_path' => $photoPath,
            ]);
        } else {
            Staff::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'sex' => $request->input('sex') ?? null,
                'dob' => $request->input('dob') ?? null,
                'address' => $request->input('address') ?? null,
                'position' => $request->input('staff_position') ?? null,
                'education_level' => $request->input('education_level') ?? null,
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

    private function generateUsername($school, $fullName)
    {
        $prefix = strtoupper($school->username_prefix);
        $surname = strtolower(explode(' ', trim($fullName))[0]);

        $lastUser = User::where('school_id', $school->id)
            ->where('username', 'LIKE', "{$prefix}-{$surname}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastUser) {
            preg_match('/(\d+)$/', $lastUser->username, $matches);
            $number = isset($matches[1]) ? ((int)$matches[1] + 1) : 1;
        } else {
            $number = 1;
        }

        return "{$prefix}-{$surname}{$number}";
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
