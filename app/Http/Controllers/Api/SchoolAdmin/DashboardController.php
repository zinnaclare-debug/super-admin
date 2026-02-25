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

        $payload = $request->validate([
            'head_of_school_name' => 'nullable|string|max:255',
            'school_location' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'head_signature' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $hasNameField = $request->has('head_of_school_name');
        $hasLocationField = $request->has('school_location');
        $hasLogoFile = $request->hasFile('logo');
        $hasSignatureFile = $request->hasFile('head_signature');

        if (!$hasNameField && !$hasLocationField && !$hasLogoFile && !$hasSignatureFile) {
            return response()->json([
                'message' => 'Provide head_of_school_name, school_location, logo, or head_signature.',
            ], 422);
        }

        if ($hasNameField) {
            $name = trim((string) ($payload['head_of_school_name'] ?? ''));
            $school->head_of_school_name = $name !== '' ? $name : null;
        }

        if ($hasLocationField) {
            $location = trim((string) ($payload['school_location'] ?? ''));
            $school->location = $location !== '' ? $location : null;
        }

        if ($hasLogoFile) {
            $logo = $request->file('logo');
            $logoExt = $logo->getClientOriginalExtension();
            $school->logo_path = $logo->storeAs("schools/{$schoolId}/branding", "logo.{$logoExt}", 'public');
        }

        if ($hasSignatureFile) {
            $signature = $request->file('head_signature');
            $signatureExt = $signature->getClientOriginalExtension();
            $school->head_signature_path = $signature->storeAs(
                "schools/{$schoolId}/branding",
                "head_signature.{$signatureExt}",
                'public'
            );
        }

        $school->save();

        return response()->json([
            'message' => 'School branding updated successfully',
            'data' => [
                'school_name' => $school->name,
                'school_location' => $school->location,
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
        $school = School::find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $payload = $request->validate([
            'ca_maxes' => 'required|array|size:5',
            'ca_maxes.*' => 'required|integer|min:0|max:100',
            'exam_max' => 'required|integer|min:0|max:100',
        ]);

        $caMaxes = array_map(fn ($value) => (int) $value, array_values($payload['ca_maxes']));
        $caTotal = array_sum($caMaxes);
        $examMax = (int) $payload['exam_max'];

        if ($caTotal <= 0) {
            return response()->json([
                'message' => 'At least one CA score must be greater than zero.',
            ], 422);
        }

        if (($caTotal + $examMax) !== 100) {
            return response()->json([
                'message' => 'Total of all CA maxima and exam maximum must be exactly 100.',
            ], 422);
        }

        $schema = AssessmentSchema::normalizeSchema([
            'ca_maxes' => $caMaxes,
            'exam_max' => $examMax,
        ]);

        $school->assessment_schema = $schema;
        $school->save();

        return response()->json([
            'message' => 'Exam record updated successfully',
            'data' => $schema,
        ]);
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
        $school = School::find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $payload = $request->validate([
            'name' => 'required|string|max:80',
        ]);

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return response()->json(['message' => 'Department name is required.'], 422);
        }

        return DB::transaction(function () use ($school, $name) {
            $templates = DepartmentTemplateSync::normalizeTemplateNames(
                $school->department_templates ?? []
            );

            $exists = collect($templates)
                ->contains(fn ($item) => strcasecmp((string) $item, $name) === 0);

            if ($exists) {
                return response()->json([
                    'message' => 'Department already exists in branding templates.',
                    'data' => $templates,
                ], 409);
            }

            $templates[] = $name;
            $school->department_templates = $templates;
            $school->save();

            DepartmentTemplateSync::syncTemplateToAllSessions((int) $school->id, $name);

            return response()->json([
                'message' => 'Department template saved and applied across all levels/classes.',
                'data' => $templates,
            ], 201);
        });
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
        $school = School::find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $payload = $request->validate([
            'class_templates' => 'required|array|size:4',
            'class_templates.*.key' => 'required|string|max:50',
            'class_templates.*.label' => 'required|string|max:80',
            'class_templates.*.enabled' => 'required|boolean',
            'class_templates.*.classes' => 'required|array|min:1|max:20',
            'class_templates.*.classes.*' => 'nullable|string|max:80',
        ]);

        $normalized = ClassTemplateSchema::normalize($payload['class_templates'] ?? []);
        $active = ClassTemplateSchema::activeSections($normalized);

        if (empty($active)) {
            return response()->json([
                'message' => 'Enable at least one class section.',
            ], 422);
        }

        foreach ($active as $section) {
            $classes = array_values(array_filter(
                (array) ($section['classes'] ?? []),
                fn ($value) => trim((string) $value) !== ''
            ));
            if (empty($classes)) {
                return response()->json([
                    'message' => 'Each enabled section must have at least one class name.',
                ], 422);
            }
        }

        $school->class_templates = $normalized;
        $school->save();

        $this->syncClassTemplatesToExistingSessions($school, $normalized);

        return response()->json([
            'message' => 'Class templates saved successfully.',
            'data' => $normalized,
        ]);
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

        $departmentTemplates = DepartmentTemplateSync::normalizeTemplateNames(
            $school->department_templates ?? []
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

                $classNames = collect((array) ($section['classes'] ?? []))
                    ->map(fn ($name) => trim((string) $name))
                    ->filter(fn ($name) => $name !== '')
                    ->values()
                    ->all();

                foreach ($classNames as $className) {
                    SchoolClass::firstOrCreate([
                        'school_id' => $schoolId,
                        'academic_session_id' => (int) $session->id,
                        'level' => $level,
                        'name' => $className,
                    ]);
                }
            }

            DepartmentTemplateSync::syncTemplatesToSession(
                $schoolId,
                (int) $session->id,
                $activeLevels,
                $departmentTemplates
            );
        }
    }
}
