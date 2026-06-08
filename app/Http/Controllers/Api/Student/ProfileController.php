<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use App\Models\Student;
use App\Models\Guardian;
use App\Models\School;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        $guardian = Guardian::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        $currentSession = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        $currentTerm = null;
        if ($currentSession) {
            $termQuery = Term::where('school_id', $schoolId)
                ->where('academic_session_id', $currentSession->id);

            if (Schema::hasColumn('terms', 'is_current')) {
                $currentTerm = (clone $termQuery)->where('is_current', true)->first();
            }

            if (!$currentTerm) {
                $currentTerm = (clone $termQuery)->orderBy('id')->first();
            }
        }

        $currentClass = null;
        $currentDepartment = null;
        if ($student && $currentTerm) {
            $classRow = Enrollment::query()
                ->where('enrollments.student_id', $student->id)
                ->where('enrollments.term_id', $currentTerm->id)
                ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
                    $q->where('enrollments.school_id', $schoolId);
                })
                ->join('classes', 'classes.id', '=', 'enrollments.class_id')
                ->leftJoin('class_departments', function ($join) use ($schoolId) {
                    $join->on('class_departments.id', '=', 'enrollments.department_id')
                        ->where('class_departments.school_id', '=', $schoolId);
                })
                ->where('classes.school_id', $schoolId)
                ->orderByDesc('enrollments.id')
                ->first([
                    'classes.id as class_id',
                    'classes.name as class_name',
                    'classes.level as class_level',
                    'class_departments.id as department_id',
                    'class_departments.name as department_name',
                ]);

            if ($classRow) {
                $currentClass = [
                    'id' => (int) $classRow->class_id,
                    'name' => $classRow->class_name,
                    'level' => $classRow->class_level,
                ];

                if (!empty($classRow->department_id)) {
                    $currentDepartment = [
                        'id' => (int) $classRow->department_id,
                        'name' => $classRow->department_name,
                    ];
                }
            }
        }

        $photoPath = $student?->photo_path ?: $user->photo_path;

        $photoUrl = null;
        if ($photoPath && Storage::disk('public')->exists($photoPath)) {
            $relativeOrAbsolute = Storage::disk('public')->url($photoPath);
            $version = Storage::disk('public')->lastModified($photoPath);
            $relativeOrAbsolute .= (str_contains($relativeOrAbsolute, '?') ? '&' : '?') . 'v=' . $version;
            $photoUrl = str_starts_with($relativeOrAbsolute, 'http://')
                || str_starts_with($relativeOrAbsolute, 'https://')
                ? $relativeOrAbsolute
                : url($relativeOrAbsolute);
        }
        $schoolName = (string) (School::query()
            ->where('id', $schoolId)
            ->value('name') ?? '');

        return response()->json([
            'data' => [
                'school_name' => $schoolName,
                'school' => [
                    'id' => $schoolId,
                    'name' => $schoolName,
                ],
                'user' => [
                    'id' => $user->id,
                    'school_id' => $user->school_id,
                    'school_name' => $schoolName,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'student' => $student,
                'guardian' => $guardian ? [
                    'name' => $guardian->name,
                    'email' => $guardian->email,
                    'mobile' => $guardian->mobile,
                    'location' => $guardian->location,
                    'state_of_origin' => $guardian->state_of_origin,
                    'occupation' => $guardian->occupation,
                    'relationship' => $guardian->relationship,
                ] : null,
                'current_session' => $currentSession ? [
                    'id' => $currentSession->id,
                    'session_name' => $currentSession->session_name,
                    'academic_year' => $currentSession->academic_year,
                    'status' => $currentSession->status,
                ] : null,
                'current_term' => $currentTerm ? [
                    'id' => $currentTerm->id,
                    'name' => $currentTerm->name,
                ] : null,
                'current_class' => $currentClass,
                'current_department' => $currentDepartment,
                'photo_path' => $photoPath,
                'photo_url' => $photoUrl,
            ]
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'student', 403);

        $schoolId = (int) $user->school_id;
        $payload = $request->validate([
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'sex' => ['nullable', 'string', 'max:30'],
            'religion' => ['nullable', 'string', 'max:80'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'guardian' => ['nullable', 'array'],
            'guardian.name' => ['nullable', 'string', 'max:255'],
            'guardian.email' => ['nullable', 'email', 'max:255'],
            'guardian.mobile' => ['nullable', 'string', 'max:30'],
            'guardian.location' => ['nullable', 'string', 'max:255'],
            'guardian.state_of_origin' => ['nullable', 'string', 'max:120'],
            'guardian.occupation' => ['nullable', 'string', 'max:120'],
            'guardian.relationship' => ['nullable', 'string', 'max:80'],
        ]);

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student record not found'], 404);
        }

        if (array_key_exists('email', $payload)) {
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $user->email = $email !== '' ? $email : $user->email;
            $user->save();
        }

        $studentUpdates = [];
        foreach (['sex', 'religion', 'dob', 'address'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = is_string($payload[$field] ?? null) ? trim((string) $payload[$field]) : ($payload[$field] ?? null);
                $studentUpdates[$field] = $value !== '' ? $value : null;
            }
        }

        if (!empty($studentUpdates)) {
            $student->fill($studentUpdates)->save();
        }

        if (array_key_exists('guardian', $payload) && is_array($payload['guardian'])) {
            $guardianPayload = $payload['guardian'];
            $guardianUpdates = [];
            foreach (['name', 'email', 'mobile', 'location', 'state_of_origin', 'occupation', 'relationship'] as $field) {
                if (array_key_exists($field, $guardianPayload)) {
                    $value = trim((string) ($guardianPayload[$field] ?? ''));
                    $guardianUpdates[$field] = $value !== '' ? $value : null;
                }
            }

            if (!empty($guardianUpdates)) {
                Guardian::updateOrCreate(
                    [
                        'user_id' => (int) $user->id,
                        'school_id' => $schoolId,
                    ],
                    $guardianUpdates
                );
            }
        }

        return $this->me($request);
    }
}
