<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\LevelDepartment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolFeature;
use App\Models\User;
use App\Support\AssessmentSchema;
use App\Support\ClassTemplateSchema;
use App\Support\DepartmentTemplateSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $school = School::find($schoolId);

        $genderStats = User::query()
            ->from('users')
            ->leftJoin('students', 'students.user_id', '=', 'users.id')
            ->where('users.school_id', $schoolId)
            ->where('users.role', 'student')
            ->selectRaw('COUNT(users.id) as total_students')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(students.sex, ''))) IN ('m', 'male') THEN 1 ELSE 0 END) as male_students")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(students.sex, ''))) IN ('f', 'female') THEN 1 ELSE 0 END) as female_students")
            ->first();

        $students = (int) ($genderStats->total_students ?? 0);
        $maleStudents = (int) ($genderStats->male_students ?? 0);
        $femaleStudents = (int) ($genderStats->female_students ?? 0);
        $unspecifiedStudents = max(0, $students - ($maleStudents + $femaleStudents));

        $staff = User::where('school_id', $schoolId)
            ->where('role', 'staff')
            ->count();

        $enabledModules = SchoolFeature::where('school_id', $schoolId)
            ->where('enabled', true)
            ->count();

        return response()->json([
            'school_name' => $school?->name,
            'school_location' => $school?->location,
            'contact_email' => $school?->contact_email,
            'contact_phone' => $school?->contact_phone,
            'school_logo_url' => $this->storageUrl($school?->logo_path),
            'head_of_school_name' => $school?->head_of_school_name,
            'head_signature_url' => $this->storageUrl($school?->head_signature_path),
            'assessment_schema' => AssessmentSchema::normalizeSchema($school?->assessment_schema),
            'department_templates' => $this->resolveDepartmentTemplates($school),
            'class_templates' => ClassTemplateSchema::normalize($school?->class_templates),
            'results_published' => (bool) ($school?->results_published),
            'students' => $students,
            'male_students' => $maleStudents,
            'female_students' => $femaleStudents,
            'unspecified_students' => $unspecifiedStudents,
            'staff' => $staff,
            'enabled_modules' => $enabledModules,
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $school = School::find($schoolId);

        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $file = $request->file('logo');
        $ext = $file->getClientOriginalExtension();
        $path = $file->storeAs("schools/{$schoolId}/branding", "logo.{$ext}", 'public');

        $school->logo_path = $path;
        $school->save();

        return response()->json([
            'message' => 'School logo uploaded successfully',
            'school_logo_url' => $this->storageUrl($school->logo_path),
            'school_name' => $school->name,
        ]);
    }

    public function upsertBranding(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $school = School::find($schoolId);

        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        if (
            $request->has('head_of_school_name')
            || $request->hasFile('logo')
            || $request->hasFile('head_signature')
        ) {
            return response()->json([
                'message' => 'Head of school name, logo, and signature are managed by Super Admin (School Information).',
            ], 403);
        }

        $payload = $request->validate([
            'school_location' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
        ]);

        $hasLocationField = $request->has('school_location');
        $hasContactEmailField = $request->has('contact_email');
        $hasContactPhoneField = $request->has('contact_phone');

        if (
            ! $hasLocationField
            && !$hasContactEmailField
            && !$hasContactPhoneField
        ) {
            return response()->json([
                'message' => 'Provide school_location, contact_email, or contact_phone.',
            ], 422);
        }

        if ($hasLocationField) {
            $location = trim((string) ($payload['school_location'] ?? ''));
            $school->location = $location !== '' ? $location : null;
        }

        if ($hasContactEmailField) {
            $contactEmail = trim((string) ($payload['contact_email'] ?? ''));
            $school->contact_email = $contactEmail !== '' ? $contactEmail : null;
        }

        if ($hasContactPhoneField) {
            $contactPhone = trim((string) ($payload['contact_phone'] ?? ''));
            $school->contact_phone = $contactPhone !== '' ? $contactPhone : null;
        }

        $school->save();

        return response()->json([
            'message' => 'School contact information updated successfully',
            'data' => [
                'school_name' => $school->name,
                'school_location' => $school->location,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'school_logo_url' => $this->storageUrl($school->logo_path),
                'head_of_school_name' => $school->head_of_school_name,
                'head_signature_url' => $this->storageUrl($school->head_signature_path),
            ],
        ]);
    }

    public function examRecord(Request $request)
    {
        $school = School::find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        return response()->json([
            'data' => AssessmentSchema::normalizeSchema($school->assessment_schema),
        ]);
    }

    public function upsertExamRecord(Request $request)
    {
        return response()->json([
            'message' => 'Exam record is managed by Super Admin (School Information).',
        ], 403);
    }

    public function departmentTemplates(Request $request)
    {
        $school = School::find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        return response()->json([
            'data' => $this->resolveDepartmentTemplates($school),
        ]);
    }

    public function storeDepartmentTemplate(Request $request)
    {
        return response()->json([
            'message' => 'Department templates are managed by Super Admin (School Information -> Class Templates).',
        ], 403);
    }

    public function updateDepartmentTemplate(Request $request)
    {
        return response()->json([
            'message' => 'Department templates are managed by Super Admin (School Information -> Class Templates).',
        ], 403);
    }

    public function deleteDepartmentTemplate(Request $request)
    {
        return response()->json([
            'message' => 'Department templates are managed by Super Admin (School Information -> Class Templates).',
        ], 403);
    }

    public function classTemplates(Request $request)
    {
        $school = School::find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        return response()->json([
            'data' => ClassTemplateSchema::normalize($school->class_templates),
        ]);
    }

    public function upsertClassTemplates(Request $request)
    {
        return response()->json([
            'message' => 'Class templates are managed by Super Admin (School Information).',
        ], 403);
    }

    private function resolveDepartmentTemplates(?School $school): array
    {
        if (!$school) {
            return [];
        }

        $templates = DepartmentTemplateSync::normalizeTemplateNames(
            $school->department_templates ?? []
        );

        if (!empty($templates)) {
            return $templates;
        }

        if (!Schema::hasTable('level_departments')) {
            return [];
        }

        return LevelDepartment::query()
            ->where('school_id', (int) $school->id)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique(fn ($name) => strtolower($name))
            ->values()
            ->all();
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

    private function syncClassTemplatesToExistingSessions(School $school, array $templates): void
    {
        $schoolId = (int) $school->id;
        $activeSections = ClassTemplateSchema::activeSections($templates);
        $activeLevels = array_values(array_map(
            fn (array $section) => strtolower(trim((string) ($section['key'] ?? ''))),
            $activeSections
        ));

        $departmentTemplateMap = DepartmentTemplateSync::normalizeClassTemplateMap(
            $school->department_templates ?? [],
            $templates
        );

        $sessions = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->get();

        foreach ($sessions as $session) {
            $session->levels = $activeLevels;
            $session->save();

            foreach ($activeSections as $section) {
                $level = strtolower(trim((string) ($section['key'] ?? '')));
                if ($level === '') {
                    continue;
                }

                $classNames = ClassTemplateSchema::activeClassNames($section);

                foreach ($classNames as $className) {
                    SchoolClass::firstOrCreate([
                        'school_id' => $schoolId,
                        'academic_session_id' => (int) $session->id,
                        'level' => $level,
                        'name' => $className,
                    ]);
                }
            }

            DepartmentTemplateSync::syncClassTemplatesToSession(
                $schoolId,
                (int) $session->id,
                $templates,
                ['by_class' => $departmentTemplateMap]
            );
        }
    }

    private function renameDepartmentAcrossSchool(int $schoolId, string $oldName, string $newName): void
    {
        $oldLower = strtolower(trim($oldName));
        $newName = trim($newName);
        if ($oldLower === '' || $newName === '') {
            return;
        }

        $newLower = strtolower($newName);
        if ($oldLower === $newLower) {
            return;
        }

        $levelDepartments = Schema::hasTable('level_departments')
            ? LevelDepartment::query()
                ->where('school_id', $schoolId)
                ->whereRaw('LOWER(name) = ?', [$oldLower])
                ->get()
            : collect();

        foreach ($levelDepartments as $department) {
            $duplicate = LevelDepartment::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $department->academic_session_id)
                ->where('level', (string) $department->level)
                ->whereRaw('LOWER(name) = ?', [$newLower])
                ->where('id', '!=', (int) $department->id)
                ->exists();

            if ($duplicate) {
                $department->delete();
                continue;
            }

            $department->name = $newName;
            $department->save();
        }

        if (!Schema::hasTable('class_departments')) {
            return;
        }

        $classDepartments = DB::table('class_departments')
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(name) = ?', [$oldLower])
            ->select(['id', 'class_id'])
            ->orderBy('id')
            ->get()
            ->groupBy('class_id');

        foreach ($classDepartments as $classId => $rows) {
            $existingTargetId = DB::table('class_departments')
                ->where('school_id', $schoolId)
                ->where('class_id', (int) $classId)
                ->whereRaw('LOWER(name) = ?', [$newLower])
                ->orderBy('id')
                ->value('id');

            $oldIds = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->values();
            if ($oldIds->isEmpty()) {
                continue;
            }

            $targetId = $existingTargetId ? (int) $existingTargetId : (int) $oldIds->first();
            if (!$existingTargetId) {
                DB::table('class_departments')
                    ->where('id', $targetId)
                    ->update([
                        'name' => $newName,
                        'updated_at' => now(),
                    ]);
            }

            $idsToDelete = $oldIds->filter(fn ($id) => $id !== $targetId)->values();
            if ($idsToDelete->isEmpty()) {
                continue;
            }

            if (Schema::hasTable('enrollments')) {
                DB::table('enrollments')
                    ->whereIn('department_id', $idsToDelete->all())
                    ->update(['department_id' => $targetId]);
            }

            DB::table('class_departments')
                ->whereIn('id', $idsToDelete->all())
                ->delete();
        }
    }

    private function removeDepartmentAcrossSchool(int $schoolId, string $name): array
    {
        $targetLower = strtolower(trim($name));
        if ($targetLower === '') {
            return [
                'removed_level_departments' => 0,
                'removed_class_departments' => 0,
                'retained_class_departments' => 0,
            ];
        }

        $removedLevelDepartments = Schema::hasTable('level_departments')
            ? LevelDepartment::query()
                ->where('school_id', $schoolId)
                ->whereRaw('LOWER(name) = ?', [$targetLower])
                ->delete()
            : 0;

        if (!Schema::hasTable('class_departments')) {
            return [
                'removed_level_departments' => (int) $removedLevelDepartments,
                'removed_class_departments' => 0,
                'retained_class_departments' => 0,
            ];
        }

        $departmentQuery = DB::table('class_departments')
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(name) = ?', [$targetLower]);

        $departmentIds = $departmentQuery
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($departmentIds->isEmpty()) {
            return [
                'removed_level_departments' => (int) $removedLevelDepartments,
                'removed_class_departments' => 0,
                'retained_class_departments' => 0,
            ];
        }

        $idsInUse = collect();
        if (Schema::hasTable('enrollments')) {
            $idsInUse = DB::table('enrollments')
                ->whereIn('department_id', $departmentIds->all())
                ->distinct()
                ->pluck('department_id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        $idsToDelete = $departmentIds
            ->reject(fn ($id) => $idsInUse->contains($id))
            ->values();

        $removedClassDepartments = 0;
        if ($idsToDelete->isNotEmpty()) {
            $removedClassDepartments = DB::table('class_departments')
                ->whereIn('id', $idsToDelete->all())
                ->delete();
        }

        return [
            'removed_level_departments' => (int) $removedLevelDepartments,
            'removed_class_departments' => (int) $removedClassDepartments,
            'retained_class_departments' => (int) $idsInUse->count(),
        ];
    }
}
