<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use App\Models\Student;
use App\Models\Guardian;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Term;

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

        $photoUrl = null;
        if ($student && $student->photo_path && Storage::disk('public')->exists($student->photo_path)) {
            $relativeOrAbsolute = Storage::disk('public')->url($student->photo_path);
            $photoUrl = str_starts_with($relativeOrAbsolute, 'http://')
                || str_starts_with($relativeOrAbsolute, 'https://')
                ? $relativeOrAbsolute
                : url($relativeOrAbsolute);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'school_id' => $user->school_id,
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
                'photo_url' => $photoUrl,
            ]
        ]);
    }
}
