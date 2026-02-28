<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolFeature;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\AssessmentSchema;
use App\Support\ClassTemplateSchema;
use App\Support\DepartmentTemplateSync;
use App\Support\UserCredentialStore;
use Illuminate\Validation\ValidationException;

class SchoolController extends Controller
{
    /**
     * Delete a school (DESTROY)
     */
    public function destroy(School $school)
    {
        DB::transaction(function () use ($school) {
            // Ensure school users are removed so their unique emails can be reused.
            User::where('school_id', $school->id)->delete();

            // Clean up explicit school feature rows.
            SchoolFeature::where('school_id', $school->id)->delete();

            $school->delete();
        });

        $this->clearApplicationCache();

        return response()->json(['message' => 'School deleted successfully.']);
    }
    /**
     * ✅ LIST ALL SCHOOLS (USED BY OVERVIEW & SCHOOLS TABLE)
     */
    public function index()
    {
        $schools = School::with([
            'admin:id,name,email,school_id',
            'features'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'data' => $schools
        ]);
    }

    /**
     * ✅ STORE (Create school directly - includes username_prefix)
     */
    public function store(Request $request)
    {
        $request->merge([
            'subdomain' => $this->normalizeSubdomain($request->input('subdomain')),
        ]);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:schools,email',
            'username_prefix'  => 'required|string|max:50|unique:schools,username_prefix',
            'subdomain'        => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+$/', 'unique:schools,subdomain'],
            'status'           => 'nullable|in:active,suspended',
        ]);

        $school = School::create($validated + [
            'slug'      => Str::slug($validated['name']),
            'status'    => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'data'    => $school,
            'message' => 'School created successfully'
        ], 201);
    }

    /**
     * ✅ CREATE SCHOOL + ADMIN (MULTI-TENANT ENTRY POINT)
     */
    public function createWithAdmin(Request $request)
    {
        $actorUserId = (int) ($request->user()->id ?? 0);

        $request->merge([
            'subdomain' => $this->normalizeSubdomain($request->input('subdomain')),
        ]);

        $validated = $request->validate([
            'school_name'  => 'required|string|max:255',
            'school_email' => 'required|email|unique:schools,email',
            'admin_name'   => 'required|string|max:255',
            'admin_email'  => 'required|email|unique:users,email',
            'subdomain'    => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+$/', 'unique:schools,subdomain'],
        ]);

        return DB::transaction(function () use ($validated, $actorUserId) {

            // 1️⃣ Create school (tenant)
            $school = School::create([
                'name'             => $validated['school_name'],
                'email'            => $validated['school_email'],
                'username_prefix'  => Str::slug($validated['school_name']),
                'slug'             => Str::slug($validated['school_name']),
                'subdomain'        => $validated['subdomain'],
                'status'           => 'active',
            ]);

            // 2️⃣ Generate password (shown ONCE)
            $plainPassword = Str::random(10);

            // 3️⃣ Create school admin
            $admin = User::create([
                'name'      => $validated['admin_name'],
                'email'     => $validated['admin_email'],
                'password'  => Hash::make($plainPassword),
                'role'      => 'school_admin',
                'school_id' => $school->id,
            ]);

            UserCredentialStore::sync(
                $admin,
                $plainPassword,
                $actorUserId > 0 ? $actorUserId : null
            );

            // 4️⃣ Seed features (use updateOrCreate to avoid duplicates if observer already created some)
// 4️⃣ Seed features (GENERAL + ADMIN)

// 4️⃣ Seed features (GENERAL + ADMIN)
$defs = config('features.definitions');

foreach ($defs as $def) {
    SchoolFeature::updateOrCreate(
        [
            'school_id' => $school->id,
            'feature'   => $def['key'],
        ],
        [
            'enabled'   => true, // super admin can later disable
            'category'  => $def['category'] ?? 'general',
        ]
    );
}




            return response()->json([
                'school'   => $school,
                'admin'    => $admin,
                'password' => $plainPassword, // show ONCE
            ], 201);
        });
    }

    /**
     * ✅ ACTIVATE / SUSPEND SCHOOL
     */
    public function toggle(School $school)
    {
        $school->status = $school->status === 'active'
            ? 'suspended'
            : 'active';

        $school->save();

        return response()->json([
            'message' => 'School status updated',
            'status'  => $school->status
        ]);
    }

    /**
     * Toggle result publication for a school (student-side visibility gate)
     */
    public function toggleResultsPublish(School $school)
    {
        $school->results_published = !$school->results_published;
        $school->save();

        return response()->json([
            'message' => 'School results publication updated',
            'results_published' => (bool) $school->results_published,
        ]);
    }

    public function information(School $school)
    {
        $normalizedClassTemplates = ClassTemplateSchema::normalize($school->class_templates);
        $departmentTemplateMapByClass = DepartmentTemplateSync::normalizeClassTemplateMap(
            $school->department_templates ?? [],
            $normalizedClassTemplates
        );
        $departmentTemplateMapByLevel = DepartmentTemplateSync::normalizeLevelTemplateMap(
            ['by_class' => $departmentTemplateMapByClass]
        );

        return response()->json([
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'email' => $school->email,
            ],
            'branding' => [
                'school_location' => $school->location,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'school_logo_url' => $this->storageUrl($school->logo_path),
                'head_of_school_name' => $school->head_of_school_name,
                'head_signature_url' => $this->storageUrl($school->head_signature_path),
            ],
            'exam_record' => AssessmentSchema::normalizeSchema($school->assessment_schema),
            'class_templates' => $this->attachDepartmentTemplatesToClassTemplates(
                $normalizedClassTemplates,
                $departmentTemplateMapByClass
            ),
            'department_templates' => DepartmentTemplateSync::flattenClassTemplateNames($departmentTemplateMapByClass),
            'department_templates_by_class' => $departmentTemplateMapByClass,
            'department_templates_by_level' => $departmentTemplateMapByLevel,
        ]);
    }

    public function upsertInformationBranding(Request $request, School $school)
    {
        $payload = $request->validate([
            'head_of_school_name' => 'nullable|string|max:255',
            'school_location' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'head_signature' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $hasNameField = $request->has('head_of_school_name');
        $hasLocationField = $request->has('school_location');
        $hasContactEmailField = $request->has('contact_email');
        $hasContactPhoneField = $request->has('contact_phone');
        $hasLogoFile = $request->hasFile('logo');
        $hasSignatureFile = $request->hasFile('head_signature');

        if (
            ! $hasNameField
            && ! $hasLocationField
            && ! $hasContactEmailField
            && ! $hasContactPhoneField
            && ! $hasLogoFile
            && ! $hasSignatureFile
        ) {
            return response()->json([
                'message' => 'Provide head_of_school_name, school_location, contact_email, contact_phone, logo, or head_signature.',
            ], 422);
        }

        $schoolId = (int) $school->id;

        if ($hasNameField) {
            $name = trim((string) ($payload['head_of_school_name'] ?? ''));
            $school->head_of_school_name = $name !== '' ? $name : null;
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
            'message' => 'School information updated successfully',
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

    public function updateInformationExamRecord(Request $request, School $school)
    {
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

    public function updateInformationClassTemplates(Request $request, School $school)
    {
        $payload = $request->validate([
            'class_templates' => 'required|array|size:4',
            'class_templates.*.key' => 'required|string|max:50',
            'class_templates.*.label' => 'required|string|max:80',
            'class_templates.*.enabled' => 'required|boolean',
            'class_templates.*.classes' => 'required|array|min:1|max:20',
            'class_templates.*.classes.*' => 'nullable',
            'class_templates.*.classes.*.name' => 'nullable|string|max:80',
            'class_templates.*.classes.*.enabled' => 'nullable|boolean',
            'class_templates.*.classes.*.department_enabled' => 'nullable|boolean',
            'class_templates.*.classes.*.department_names' => 'nullable|array|max:30',
            'class_templates.*.classes.*.department_names.*' => 'nullable|string|max:80',
        ]);

        $normalized = ClassTemplateSchema::normalize($payload['class_templates'] ?? []);
        $active = ClassTemplateSchema::activeSections($normalized);

        if (empty($active)) {
            return response()->json([
                'message' => 'Enable at least one class section.',
            ], 422);
        }

        foreach ($active as $section) {
            $classes = ClassTemplateSchema::activeClassNames($section);
            if (empty($classes)) {
                return response()->json([
                    'message' => 'Each enabled section must have at least one checked class.',
                ], 422);
            }
        }

        $departmentTemplateMapByClass = $this->buildDepartmentTemplateMapFromClassTemplates(
            $payload['class_templates'] ?? [],
            $normalized
        );

        $school->class_templates = $normalized;
        $school->department_templates = DepartmentTemplateSync::serializeClassTemplateMap(
            $departmentTemplateMapByClass,
            $normalized
        );
        $school->save();

        $this->syncClassTemplatesToExistingSessions($school, $normalized, $departmentTemplateMapByClass);

        $departmentTemplateMapByLevel = DepartmentTemplateSync::normalizeLevelTemplateMap([
            'by_class' => $departmentTemplateMapByClass,
        ]);

        return response()->json([
            'message' => 'Class templates saved successfully.',
            'data' => $this->attachDepartmentTemplatesToClassTemplates($normalized, $departmentTemplateMapByClass),
            'department_templates_by_class' => $departmentTemplateMapByClass,
            'department_templates_by_level' => $departmentTemplateMapByLevel,
        ]);
    }

    /**
     * List academic sessions for one school.
     */
    public function academicSessions(School $school)
    {
        $sessions = AcademicSession::query()
            ->where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
            ],
            'data' => $sessions,
        ]);
    }

    /**
     * Super admin controls session lifecycle: pending -> current -> completed.
     */
    public function updateAcademicSessionStatus(Request $request, School $school, AcademicSession $session)
    {
        if ((int) $session->school_id !== (int) $school->id) {
            return response()->json(['message' => 'Session does not belong to this school.'], 422);
        }

        $payload = $request->validate([
            'status' => 'required|in:pending,current,completed',
        ]);

        return DB::transaction(function () use ($school, $session, $payload) {
            $status = $payload['status'];

            if ($status === 'current') {
                AcademicSession::query()
                    ->where('school_id', $school->id)
                    ->where('status', 'current')
                    ->where('id', '!=', $session->id)
                    ->update(['status' => 'completed']);

                $hasCurrentTerm = Term::query()
                    ->where('school_id', $school->id)
                    ->where('academic_session_id', $session->id)
                    ->where('is_current', true)
                    ->exists();

                if (! $hasCurrentTerm) {
                    Term::query()
                        ->where('school_id', $school->id)
                        ->where('academic_session_id', $session->id)
                        ->update(['is_current' => false]);

                    $firstTerm = Term::query()
                        ->where('school_id', $school->id)
                        ->where('academic_session_id', $session->id)
                        ->orderBy('id')
                        ->first();

                    if ($firstTerm) {
                        $firstTerm->update(['is_current' => true]);
                    }
                }
            } else {
                // Non-current sessions should not own a current term.
                Term::query()
                    ->where('school_id', $school->id)
                    ->where('academic_session_id', $session->id)
                    ->update(['is_current' => false]);
            }

            $session->update(['status' => $status]);

            return response()->json(['data' => $session]);
        });
    }

    /**
     * Delete a school's academic session.
     */
    public function destroyAcademicSession(School $school, AcademicSession $session)
    {
        if ((int) $session->school_id !== (int) $school->id) {
            return response()->json(['message' => 'Session does not belong to this school.'], 422);
        }

        $session->delete();

        return response()->json([
            'message' => 'Academic session deleted successfully.',
        ]);
    }

    private function normalizeSubdomain(?string $subdomain): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $subdomain))) ?? '';
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);

        return str_starts_with($relativeOrAbsolute, 'http://')
            || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
    }

    private function syncClassTemplatesToExistingSessions(
        School $school,
        array $templates,
        ?array $departmentTemplateMapByClass = null
    ): void
    {
        $schoolId = (int) $school->id;
        $activeSections = ClassTemplateSchema::activeSections($templates);
        $activeLevels = array_values(array_map(
            fn (array $section) => strtolower(trim((string) ($section['key'] ?? ''))),
            $activeSections
        ));

        $departmentTemplateMapByClass = $departmentTemplateMapByClass
            ? DepartmentTemplateSync::normalizeClassTemplateMap(['by_class' => $departmentTemplateMapByClass], $templates)
            : DepartmentTemplateSync::normalizeClassTemplateMap($school->department_templates ?? [], $templates);

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
                ['by_class' => $departmentTemplateMapByClass]
            );
        }
    }

    private function buildDepartmentTemplateMapFromClassTemplates(array $rawTemplates, array $normalizedTemplates): array
    {
        $map = DepartmentTemplateSync::emptyClassTemplateMap($normalizedTemplates);
        $normalizedByKey = collect($normalizedTemplates)->keyBy(
            fn (array $section) => strtolower(trim((string) ($section['key'] ?? '')))
        );
        $rawByKey = collect($rawTemplates)
            ->filter(fn ($section) => is_array($section))
            ->keyBy(fn (array $section) => strtolower(trim((string) ($section['key'] ?? ''))));

        foreach ($normalizedByKey as $key => $normalizedSection) {
            if (!array_key_exists($key, $map)) {
                continue;
            }

            $sectionEnabled = (bool) ($normalizedSection['enabled'] ?? false);
            $rawSection = $rawByKey->get($key, []);
            $rawClasses = is_array($rawSection['classes'] ?? null) ? $rawSection['classes'] : [];
            $normalizedClasses = is_array($normalizedSection['classes'] ?? null) ? $normalizedSection['classes'] : [];

            foreach ($normalizedClasses as $classIndex => $normalizedClassRow) {
                $className = trim((string) ($normalizedClassRow['name'] ?? ''));
                if ($className === '') {
                    continue;
                }

                $classEnabled = $sectionEnabled && (bool) ($normalizedClassRow['enabled'] ?? false);
                $rawClassRow = $this->resolveRawClassRow($rawClasses, $classIndex, $className);
                $departmentEnabled = filter_var(
                    $rawClassRow['department_enabled'] ?? $classEnabled,
                    FILTER_VALIDATE_BOOLEAN
                );
                $departmentNames = DepartmentTemplateSync::normalizeTemplateNames(
                    $rawClassRow['department_names'] ?? []
                );

                if ($classEnabled && $departmentEnabled && empty($departmentNames)) {
                    throw ValidationException::withMessages([
                        'class_templates' => [
                            "Provide at least one department for {$className} or disable its department checkbox.",
                        ],
                    ]);
                }

                $map[$key][$className] = [
                    'enabled' => $classEnabled && $departmentEnabled && !empty($departmentNames),
                    'names' => $departmentNames,
                ];
            }
        }

        return $map;
    }

    private function resolveRawClassRow(array $rawClasses, int $classIndex, string $className): array
    {
        $byIndex = $rawClasses[$classIndex] ?? null;
        if (is_array($byIndex)) {
            return $byIndex;
        }

        $needle = strtolower(trim($className));
        foreach ($rawClasses as $rawClassRow) {
            if (!is_array($rawClassRow)) {
                continue;
            }

            $rawName = strtolower(trim((string) ($rawClassRow['name'] ?? '')));
            if ($rawName === $needle) {
                return $rawClassRow;
            }
        }

        return [];
    }

    private function attachDepartmentTemplatesToClassTemplates(array $templates, array $departmentTemplateMapByClass): array
    {
        return collect(ClassTemplateSchema::normalize($templates))
            ->map(function (array $section) use ($departmentTemplateMapByClass) {
                $level = strtolower(trim((string) ($section['key'] ?? '')));
                $classes = is_array($section['classes'] ?? null) ? $section['classes'] : [];
                $section['classes'] = collect($classes)
                    ->map(function ($classRow) use ($departmentTemplateMapByClass, $level) {
                        $name = trim((string) (is_array($classRow) ? ($classRow['name'] ?? '') : ''));
                        $enabled = (bool) (is_array($classRow) ? ($classRow['enabled'] ?? false) : false);
                        $departmentRow = $this->resolveDepartmentRowForClass(
                            $departmentTemplateMapByClass,
                            $level,
                            $name
                        );

                        return [
                            'name' => $name,
                            'enabled' => $enabled,
                            'department_enabled' => $enabled && (bool) ($departmentRow['enabled'] ?? false),
                            'department_names' => $departmentRow['names'] ?? [],
                        ];
                    })
                    ->values()
                    ->all();

                return $section;
            })
            ->values()
            ->all();
    }

    private function resolveDepartmentRowForClass(
        array $departmentTemplateMapByClass,
        string $level,
        string $className
    ): array {
        $rows = is_array($departmentTemplateMapByClass[$level] ?? null)
            ? $departmentTemplateMapByClass[$level]
            : [];
        $needle = strtolower(trim($className));
        foreach ($rows as $rowName => $row) {
            if (strtolower(trim((string) $rowName)) !== $needle) {
                continue;
            }

            if (!is_array($row)) {
                break;
            }

            return [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'names' => is_array($row['names'] ?? null) ? array_values($row['names']) : [],
            ];
        }

        return [
            'enabled' => false,
            'names' => [],
        ];
    }

    private function clearApplicationCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            Log::warning('Cache flush failed after school deletion: ' . $e->getMessage());
        }
    }
}
