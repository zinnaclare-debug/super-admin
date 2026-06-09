<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSchoolClassTemplatesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
use App\Support\GradingSchema;
use App\Support\SchoolHistoryImportService;
use App\Support\SchoolSubscriptionBilling;
use App\Support\UserCredentialStore;
use Illuminate\Validation\ValidationException;

class SchoolController extends Controller
{

    /**
     * Delete a school (DESTROY)
     */
    public function destroy(Request $request, School $school)
    {
        $this->validateDeleteCode($request);
        $schoolId = (int) $school->id;

        DB::transaction(function () use ($school, $schoolId) {
            User::where('school_id', $schoolId)->delete();
            SchoolFeature::where('school_id', $schoolId)->delete();
            $school->delete();
            $this->deleteResidualSchoolRows($schoolId);
        });

        $this->deleteSchoolStorage($schoolId);
        $this->clearApplicationCache();

        return response()->json(['message' => 'School deleted successfully.']);
    }

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

    public function update(Request $request, School $school)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:schools,email,' . $school->id,
        ]);

        $school->name = $payload['name'];
        $school->email = $payload['email'];
        $school->slug = Str::slug($payload['name']);
        $school->save();

        return response()->json([
            'message' => 'School updated successfully',
            'data' => $school,
        ]);
    }
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
            $school = School::create([
                'name'             => $validated['school_name'],
                'email'            => $validated['school_email'],
                'username_prefix'  => Str::slug($validated['school_name']),
                'slug'             => Str::slug($validated['school_name']),
                'subdomain'        => $validated['subdomain'],
                'status'           => 'active',
            ]);

            $plainPassword = Str::random(10);

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

            $defs = config('features.definitions');

            foreach ($defs as $def) {
                SchoolFeature::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'feature'   => $def['key'],
                    ],
                    [
                        'enabled'   => true,
                        'category'  => $def['category'] ?? 'general',
                    ]
                );
            }

            return response()->json([
                'school'   => $school,
                'admin'    => $admin,
                'password' => $plainPassword,
            ], 201);
        });
    }

    public function toggle(Request $request, School $school)
    {
        $this->validateDeleteCode($request);

        $school->status = $school->status === 'active'
            ? 'suspended'
            : 'active';

        $school->save();

        return response()->json([
            'message' => 'School status updated',
            'status'  => $school->status
        ]);
    }

    public function toggleResultsPublish(Request $request, School $school)
    {
        $this->validateDeleteCode($request);

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
                'paystack_subaccount_code' => $school->paystack_subaccount_code,
                'school_logo_url' => $this->storageUrl($school->logo_path),
                'head_of_school_name' => $school->head_of_school_name,
                'head_signature_url' => $this->storageUrl($school->head_signature_path),
            ],
            'exam_record' => AssessmentSchema::normalizeLevelSchemas(
                $school->assessment_schema,
                ClassTemplateSchema::activeLevelKeys($normalizedClassTemplates)
            ),
            'grading_schema' => GradingSchema::normalize($school->grading_schema),
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
            'paystack_subaccount_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:150',
            'head_signature' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:150',
        ]);

        $hasNameField = $request->has('head_of_school_name');
        $hasLocationField = $request->has('school_location');
        $hasContactEmailField = $request->has('contact_email');
        $hasContactPhoneField = $request->has('contact_phone');
        $hasPaystackSubaccountField = $request->has('paystack_subaccount_code');
        $hasLogoFile = $request->hasFile('logo');
        $hasSignatureFile = $request->hasFile('head_signature');

        if (
            ! $hasNameField
            && ! $hasLocationField
            && ! $hasContactEmailField
            && ! $hasContactPhoneField
            && ! $hasPaystackSubaccountField
            && ! $hasLogoFile
            && ! $hasSignatureFile
        ) {
            return response()->json([
                'message' => 'Provide head_of_school_name, school_location, contact_email, contact_phone, paystack_subaccount_code, logo, or head_signature.',
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
        if ($hasPaystackSubaccountField) {
            $subaccountCode = trim((string) ($payload['paystack_subaccount_code'] ?? ''));
            $school->paystack_subaccount_code = $subaccountCode !== '' ? $subaccountCode : null;
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
                'paystack_subaccount_code' => $school->paystack_subaccount_code,
                'school_logo_url' => $this->storageUrl($school->logo_path),
                'head_of_school_name' => $school->head_of_school_name,
                'head_signature_url' => $this->storageUrl($school->head_signature_path),
            ],
        ]);
    }

    public function updateInformationExamRecord(Request $request, School $school)
    {
        $payload = $request->validate([
            'ca_maxes' => 'nullable|array|size:5',
            'ca_maxes.*' => 'required_with:ca_maxes|integer|min:0|max:100',
            'exam_max' => 'nullable|integer|min:0|max:100',
            'by_level' => 'nullable|array',
            'by_level.*.ca_maxes' => 'required_with:by_level|array|size:5',
            'by_level.*.ca_maxes.*' => 'required|integer|min:0|max:100',
            'by_level.*.exam_max' => 'required_with:by_level|integer|min:0|max:100',
        ]);

        $normalizedClassTemplates = ClassTemplateSchema::normalize($school->class_templates);
        $activeLevels = ClassTemplateSchema::activeLevelKeys($normalizedClassTemplates);

        if (!empty($payload['by_level']) && is_array($payload['by_level'])) {
            $byLevel = [];
            $levelErrors = [];

            foreach ($activeLevels as $levelKey) {
                $submitted = $payload['by_level'][$levelKey] ?? null;
                if (!is_array($submitted)) {
                    $levelErrors[] = "Exam record for {$levelKey} is required.";
                    continue;
                }

                $caMaxes = array_map(fn ($value) => (int) $value, array_values($submitted['ca_maxes'] ?? []));
                $examMax = (int) ($submitted['exam_max'] ?? 0);
                $validationMessage = $this->validateAssessmentSchemaParts($caMaxes, $examMax);
                if ($validationMessage !== null) {
                    $levelErrors[] = "{$levelKey}: {$validationMessage}";
                    continue;
                }

                $byLevel[$levelKey] = AssessmentSchema::normalizeSchema([
                    'ca_maxes' => $caMaxes,
                    'exam_max' => $examMax,
                ]);
            }

            if (!empty($levelErrors)) {
                return response()->json([
                    'message' => implode(' ', $levelErrors),
                ], 422);
            }

            $existing = AssessmentSchema::normalizeLevelSchemas($school->assessment_schema, $activeLevels);
            $schema = [
                'default' => AssessmentSchema::normalizeSchema($existing['default'] ?? []),
                'by_level' => $byLevel,
            ];
        } else {
            $caMaxes = array_map(fn ($value) => (int) $value, array_values($payload['ca_maxes'] ?? []));
            $examMax = (int) ($payload['exam_max'] ?? 0);
            $validationMessage = $this->validateAssessmentSchemaParts($caMaxes, $examMax);

            if ($validationMessage !== null) {
                return response()->json([
                    'message' => $validationMessage,
                ], 422);
            }

            $default = AssessmentSchema::normalizeSchema([
                'ca_maxes' => $caMaxes,
                'exam_max' => $examMax,
            ]);
            $schema = AssessmentSchema::normalizeLevelSchemas([
                'default' => $default,
                'by_level' => array_fill_keys($activeLevels, $default),
            ], $activeLevels);
        }

        $school->assessment_schema = $schema;
        $school->save();

        return response()->json([
            'message' => 'Exam record updated successfully',
            'data' => AssessmentSchema::normalizeLevelSchemas($school->assessment_schema, $activeLevels),
        ]);
    }

    public function updateInformationGradingSchema(Request $request, School $school)
    {
        $payload = $request->validate([
            'grading_schema' => 'required|array|max:' . GradingSchema::MAX_ROWS,
            'grading_schema.*.from' => 'nullable|integer|min:0|max:100',
            'grading_schema.*.to' => 'nullable|integer|min:0|max:100',
            'grading_schema.*.grade' => 'nullable|string|max:20',
            'grading_schema.*.remark' => 'nullable|string|max:120',
        ]);

        $schema = GradingSchema::validateAndNormalize($payload['grading_schema'] ?? []);

        $school->grading_schema = $schema;
        $school->save();

        return response()->json([
            'message' => 'Grading system updated successfully.',
            'data' => $schema,
        ]);
    }

    private function validateAssessmentSchemaParts(array $caMaxes, int $examMax): ?string
    {
        if (count($caMaxes) !== 5) {
            return 'CA maxima must contain CA1 to CA5.';
        }

        $caTotal = array_sum(array_map(fn ($value) => (int) $value, $caMaxes));

        if ($caTotal <= 0) {
            return 'At least one CA score must be greater than zero.';
        }

        if (($caTotal + $examMax) !== 100) {
            return 'Total of all CA maxima and exam maximum must be exactly 100.';
        }

        return null;
    }

    public function updateInformationClassTemplates(Request $request, School $school)
    {
        $payload = $request->validate([
            'class_templates' => 'required|array|size:5',
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

        $previousTemplates = ClassTemplateSchema::normalize($school->class_templates);
        $previousDepartmentTemplateMapByClass = DepartmentTemplateSync::normalizeClassTemplateMap(
            $school->department_templates ?? [],
            $previousTemplates
        );
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

        DB::transaction(function () use (
            $school,
            $normalized,
            $departmentTemplateMapByClass
        ) {
            $school->class_templates = $normalized;
            $school->department_templates = DepartmentTemplateSync::serializeClassTemplateMap(
                $departmentTemplateMapByClass,
                $normalized
            );
            $school->save();
        });

        SyncSchoolClassTemplatesJob::dispatch(
            (int) $school->id,
            $normalized,
            $departmentTemplateMapByClass,
            $previousTemplates,
            $previousDepartmentTemplateMapByClass
        );

        $departmentTemplateMapByLevel = DepartmentTemplateSync::normalizeLevelTemplateMap([
            'by_class' => $departmentTemplateMapByClass,
        ]);

        return response()->json([
            'message' => 'Class templates saved successfully. Existing sessions are syncing in the background.',
            'data' => $this->attachDepartmentTemplatesToClassTemplates($normalized, $departmentTemplateMapByClass),
            'department_templates_by_class' => $departmentTemplateMapByClass,
            'department_templates_by_level' => $departmentTemplateMapByLevel,
        ]);
    }

    public function downloadHistoryImportTemplate(School $school)
    {
        $fileName = Str::slug($school->name ?: 'school') . '-history-import-template.csv';

        return response(SchoolHistoryImportService::templateCsv($school), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function importHistory(Request $request, School $school, SchoolHistoryImportService $importer)
    {
        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'make_latest_session_current' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $importer->import(
                $school,
                $payload['file'],
                (int) $request->user()->id,
                (bool) ($payload['make_latest_session_current'] ?? false)
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'History import failed. Check session, term, class, level, subject, and score spelling. '
                    . 'Technical detail: ' . Str::limit($e->getMessage(), 180),
            ], 422);
        }

        return response()->json([
            'message' => 'School history imported successfully.',
            'data' => $result,
        ]);
    }

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

    public function updateAcademicSessionStatus(Request $request, School $school, AcademicSession $session)
    {
        if ((int) $session->school_id !== (int) $school->id) {
            return response()->json(['message' => 'Session does not belong to this school.'], 422);
        }

        $payload = $request->validate([
            'status' => 'required|in:pending,current,completed',
            'current_selection_code' => ['nullable', 'digits:4'],
        ]);

        return DB::transaction(function () use ($school, $session, $payload) {
            $status = $payload['status'];

            if ($status === 'current') {
                $this->validateCurrentSelectionCode($payload);

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

                $school->results_published = false;
            } else {
                Term::query()
                    ->where('school_id', $school->id)
                    ->where('academic_session_id', $session->id)
                    ->update(['is_current' => false]);
            }

            $session->update(['status' => $status]);
            $school->save();

            $settings = SchoolSubscriptionBilling::getSettings($school);
            SchoolSubscriptionBilling::clearPendingOverride($settings);

            return response()->json([
                'data' => $session,
                'results_published' => (bool) $school->results_published,
            ]);
        });
    }

    public function destroyAcademicSession(Request $request, School $school, AcademicSession $session)
    {
        if ((int) $session->school_id !== (int) $school->id) {
            return response()->json(['message' => 'Session does not belong to this school.'], 422);
        }

        $this->validateDeleteCode($request);

        $session->delete();

        return response()->json([
            'message' => 'Academic session deleted successfully.',
        ]);
    }

    private function validateCurrentSelectionCode(array $payload): void
    {
        $code = (string) ($payload['current_selection_code'] ?? '');

        if (! hash_equals('4722', $code)) {
            throw ValidationException::withMessages([
                'current_selection_code' => ['Invalid current selection confirmation code.'],
            ]);
        }
    }

    private function validateDeleteCode(Request $request): void
    {
        $validated = $request->validate([
            'delete_code' => ['required', 'digits:4'],
        ]);

        $expectedDeleteCode = (string) config('app.super_admin_delete_confirmation_code', '4722');

        if (!hash_equals($expectedDeleteCode, (string) $validated['delete_code'])) {
            throw ValidationException::withMessages([
                'delete_code' => ['Invalid delete confirmation code.'],
            ]);
        }
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

    public function syncClassTemplatesToExistingSessions(
        School $school,
        array $templates,
        ?array $departmentTemplateMapByClass = null,
        ?array $previousTemplates = null,
        ?array $previousDepartmentTemplateMapByClass = null
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
        $previousTemplates = ClassTemplateSchema::normalize($previousTemplates ?? []);
        $previousDepartmentTemplateMapByClass = DepartmentTemplateSync::normalizeClassTemplateMap(
            ['by_class' => ($previousDepartmentTemplateMapByClass ?? [])],
            $previousTemplates
        );
        $newRowsByLevel = $this->indexedClassTemplateRows($templates);
        $previousRowsByLevel = $this->indexedClassTemplateRows($previousTemplates);

        $sessions = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->get();

        foreach ($sessions as $session) {
            $session->levels = $activeLevels;
            $session->save();

            foreach ($newRowsByLevel as $level => $rows) {
                foreach ($rows as $index => $row) {
                    if (!($row['enabled'] ?? false)) {
                        continue;
                    }

                    $class = $this->syncTemplateClassRow(
                        $schoolId,
                        (int) $session->id,
                        $level,
                        trim((string) ($row['name'] ?? '')),
                        $previousRowsByLevel[$level][$index]['name'] ?? null
                    );

                    if ($class) {
                        $this->syncTemplateClassDepartments(
                            $schoolId,
                            (int) $session->id,
                            $level,
                            $class,
                            $this->departmentNamesForTemplateClass($departmentTemplateMapByClass, $level, (string) $row['name']),
                            $this->departmentNamesForTemplateClass(
                                $previousDepartmentTemplateMapByClass,
                                $level,
                                (string) ($previousRowsByLevel[$level][$index]['name'] ?? '')
                            )
                        );
                    }
                }
            }

            $this->deactivateClassesOutsideCurrentTemplate($schoolId, (int) $session->id, $newRowsByLevel);
            $this->deactivateLevelDepartmentsOutsideCurrentTemplate(
                $schoolId,
                (int) $session->id,
                $departmentTemplateMapByClass
            );
            $this->removeUncheckedTemplateRows(
                $schoolId,
                (int) $session->id,
                $previousRowsByLevel,
                $newRowsByLevel,
                $previousDepartmentTemplateMapByClass
            );
        }
    }

    private function indexedClassTemplateRows(array $templates): array
    {
        $rowsByLevel = [];

        foreach (ClassTemplateSchema::normalize($templates) as $section) {
            $level = strtolower(trim((string) ($section['key'] ?? '')));
            if ($level === '') {
                continue;
            }

            $sectionEnabled = (bool) ($section['enabled'] ?? false);
            $rowsByLevel[$level] = [];
            $classes = is_array($section['classes'] ?? null) ? $section['classes'] : [];

            foreach ($classes as $index => $classRow) {
                $name = trim((string) (is_array($classRow) ? ($classRow['name'] ?? '') : $classRow));
                if ($name === '') {
                    continue;
                }

                $rowsByLevel[$level][$index] = [
                    'name' => $name,
                    'enabled' => $sectionEnabled && (bool) (is_array($classRow) ? ($classRow['enabled'] ?? true) : true),
                ];
            }
        }

        return $rowsByLevel;
    }

    private function syncTemplateClassRow(
        int $schoolId,
        int $sessionId,
        string $level,
        string $newName,
        ?string $previousName = null
    ): ?SchoolClass {
        if ($newName === '') {
            return null;
        }

        $existing = $this->findSessionClass($schoolId, $sessionId, $level, $newName);
        $previousName = trim((string) $previousName);
        $previous = $previousName !== '' && strcasecmp($previousName, $newName) !== 0
            ? $this->findSessionClass($schoolId, $sessionId, $level, $previousName)
            : null;

        if ($previous) {
            if ($existing && (int) $existing->id !== (int) $previous->id) {
                if (!$this->classHasRealUsage((int) $existing->id)) {
                    $existing->delete();
                    $previous->name = $newName;
                    $this->setModelTemplateActive($previous, 'classes', true);
                    $previous->save();

                    return $previous->fresh();
                }

                if (!$this->classHasRealUsage((int) $previous->id)) {
                    $previous->delete();
                    $this->setModelTemplateActive($existing, 'classes', true);
                    $existing->save();

                    return $existing;
                }

                $this->setModelTemplateActive($existing, 'classes', true);
                $existing->save();
                return $existing;
            }

            $previous->name = $newName;
            $this->setModelTemplateActive($previous, 'classes', true);
            $previous->save();

            return $previous->fresh();
        }

        if ($existing) {
            $this->setModelTemplateActive($existing, 'classes', true);
            $existing->save();
            return $existing;
        }

        return SchoolClass::query()->create(array_merge([
            'school_id' => $schoolId,
            'academic_session_id' => $sessionId,
            'level' => $level,
            'name' => $newName,
        ], $this->templateActiveValues('classes', true)));
    }

    private function findSessionClass(int $schoolId, int $sessionId, string $level, string $name): ?SchoolClass
    {
        return SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('level', $level)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();
    }

    private function syncTemplateClassDepartments(
        int $schoolId,
        int $sessionId,
        string $level,
        SchoolClass $class,
        array $newNames,
        array $previousNames
    ): void {
        foreach ($newNames as $index => $newName) {
            $newName = trim((string) $newName);
            if ($newName === '') {
                continue;
            }

            $previousName = trim((string) ($previousNames[$index] ?? ''));
            $this->syncLevelDepartmentRow($schoolId, $sessionId, $level, $newName, $previousName);
            $this->syncClassDepartmentRow($schoolId, (int) $class->id, $newName, $previousName);
        }

        foreach ($previousNames as $index => $previousName) {
            $previousName = trim((string) $previousName);
            $newName = trim((string) ($newNames[$index] ?? ''));
            if ($previousName === '' || $newName !== '') {
                continue;
            }

            $this->deleteClassDepartmentIfUnused($schoolId, (int) $class->id, $previousName);
            $this->deleteLevelDepartmentIfUnused($schoolId, $sessionId, $level, $previousName);
        }

        $this->deactivateClassDepartmentsOutsideTemplate($schoolId, (int) $class->id, $newNames);
    }

    private function syncLevelDepartmentRow(
        int $schoolId,
        int $sessionId,
        string $level,
        string $newName,
        string $previousName = ''
    ): void {
        $existing = $this->findLevelDepartment($schoolId, $sessionId, $level, $newName);
        $previous = $previousName !== '' && strcasecmp($previousName, $newName) !== 0
            ? $this->findLevelDepartment($schoolId, $sessionId, $level, $previousName)
            : null;

        if ($previous) {
            if ($existing && (int) $existing->id !== (int) $previous->id) {
                DB::table('level_departments')->where('id', (int) $previous->id)->delete();
                DB::table('level_departments')->where('id', (int) $existing->id)->update(array_merge(
                    $this->templateActiveValues('level_departments', true),
                    ['updated_at' => now()]
                ));
                return;
            }

            DB::table('level_departments')->where('id', (int) $previous->id)->update([
                'name' => $newName,
                ...$this->templateActiveValues('level_departments', true),
                'updated_at' => now(),
            ]);
            return;
        }

        if (!$existing) {
            DB::table('level_departments')->insert(array_merge([
                'school_id' => $schoolId,
                'academic_session_id' => $sessionId,
                'level' => $level,
                'name' => $newName,
                'created_at' => now(),
                'updated_at' => now(),
            ], $this->templateActiveValues('level_departments', true)));
        } else {
            DB::table('level_departments')->where('id', (int) $existing->id)->update(array_merge(
                $this->templateActiveValues('level_departments', true),
                ['updated_at' => now()]
            ));
        }
    }

    private function syncClassDepartmentRow(
        int $schoolId,
        int $classId,
        string $newName,
        string $previousName = ''
    ): void {
        $existing = $this->findClassDepartment($schoolId, $classId, $newName);
        $previous = $previousName !== '' && strcasecmp($previousName, $newName) !== 0
            ? $this->findClassDepartment($schoolId, $classId, $previousName)
            : null;

        if ($previous) {
            if ($existing && (int) $existing->id !== (int) $previous->id) {
                $this->mergeClassDepartmentRows((int) $previous->id, (int) $existing->id);
                return;
            }

            DB::table('class_departments')->where('id', (int) $previous->id)->update([
                'name' => $newName,
                ...$this->templateActiveValues('class_departments', true),
                'updated_at' => now(),
            ]);
            return;
        }

        if (!$existing) {
            DB::table('class_departments')->insert(array_merge([
                'school_id' => $schoolId,
                'class_id' => $classId,
                'name' => $newName,
                'created_at' => now(),
                'updated_at' => now(),
            ], $this->templateActiveValues('class_departments', true)));
        } else {
            DB::table('class_departments')->where('id', (int) $existing->id)->update(array_merge(
                $this->templateActiveValues('class_departments', true),
                ['updated_at' => now()]
            ));
        }
    }

    private function findLevelDepartment(int $schoolId, int $sessionId, string $level, string $name): ?object
    {
        return DB::table('level_departments')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('level', $level)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();
    }

    private function findClassDepartment(int $schoolId, int $classId, string $name): ?object
    {
        return DB::table('class_departments')
            ->where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();
    }

    private function mergeClassDepartmentRows(int $sourceId, int $targetId): void
    {
        if (Schema::hasTable('enrollments') && Schema::hasColumn('enrollments', 'department_id')) {
            DB::table('enrollments')->where('department_id', $sourceId)->update([
                'department_id' => $targetId,
                'updated_at' => now(),
            ]);
        }

        $sourceTeacherId = Schema::hasColumn('class_departments', 'class_teacher_user_id')
            ? DB::table('class_departments')->where('id', $sourceId)->value('class_teacher_user_id')
            : null;

        if ($sourceTeacherId && Schema::hasColumn('class_departments', 'class_teacher_user_id')) {
            DB::table('class_departments')
                ->where('id', $targetId)
                ->whereNull('class_teacher_user_id')
                ->update(['class_teacher_user_id' => $sourceTeacherId, 'updated_at' => now()]);
        }

        DB::table('class_departments')->where('id', $sourceId)->delete();
        DB::table('class_departments')->where('id', $targetId)->update(array_merge(
            $this->templateActiveValues('class_departments', true),
            ['updated_at' => now()]
        ));
    }

    private function removeUncheckedTemplateRows(
        int $schoolId,
        int $sessionId,
        array $previousRowsByLevel,
        array $newRowsByLevel,
        array $previousDepartmentTemplateMapByClass
    ): void {
        foreach ($previousRowsByLevel as $level => $previousRows) {
            foreach ($previousRows as $index => $previousRow) {
                if (!($previousRow['enabled'] ?? false)) {
                    continue;
                }

                $newRow = $newRowsByLevel[$level][$index] ?? null;
                if ($newRow && ($newRow['enabled'] ?? false)) {
                    continue;
                }

                $class = $this->findSessionClass($schoolId, $sessionId, $level, (string) ($previousRow['name'] ?? ''));
                if (!$class) {
                    continue;
                }

                foreach ($this->departmentNamesForTemplateClass(
                    $previousDepartmentTemplateMapByClass,
                    $level,
                    (string) ($previousRow['name'] ?? '')
                ) as $departmentName) {
                    $this->deleteClassDepartmentIfUnused($schoolId, (int) $class->id, $departmentName);
                    $this->deleteLevelDepartmentIfUnused($schoolId, $sessionId, $level, $departmentName);
                }

                if ($this->classHasRealUsage((int) $class->id)) {
                    $this->markTemplateInactive('classes', (int) $class->id);
                    continue;
                }

                $class->delete();
            }
        }
    }

    private function deactivateClassesOutsideCurrentTemplate(int $schoolId, int $sessionId, array $newRowsByLevel): void
    {
        if (!Schema::hasColumn('classes', 'is_template_active')) {
            return;
        }

        foreach ($newRowsByLevel as $level => $rows) {
            $activeNames = collect($rows)
                ->filter(fn (array $row) => (bool) ($row['enabled'] ?? false))
                ->map(fn (array $row) => strtolower(trim((string) ($row['name'] ?? ''))))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $classes = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $sessionId)
                ->where('level', $level)
                ->get(['id', 'name']);

            foreach ($classes as $class) {
                $className = strtolower(trim((string) $class->name));
                if (in_array($className, $activeNames, true)) {
                    continue;
                }

                $this->markTemplateInactive('classes', (int) $class->id);
                $this->deactivateAllClassDepartments($schoolId, (int) $class->id);
            }
        }
    }

    private function deactivateClassDepartmentsOutsideTemplate(int $schoolId, int $classId, array $activeNames): void
    {
        if (!Schema::hasColumn('class_departments', 'is_template_active')) {
            return;
        }

        $activeNames = collect($activeNames)
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::table('class_departments')
            ->where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->get(['id', 'name'])
            ->each(function ($department) use ($activeNames) {
                if (in_array(strtolower(trim((string) $department->name)), $activeNames, true)) {
                    return;
                }

                $this->markTemplateInactive('class_departments', (int) $department->id);
            });
    }

    private function deactivateAllClassDepartments(int $schoolId, int $classId): void
    {
        if (!Schema::hasColumn('class_departments', 'is_template_active')) {
            return;
        }

        DB::table('class_departments')
            ->where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->update([
                'is_template_active' => false,
                'updated_at' => now(),
            ]);
    }

    private function deactivateLevelDepartmentsOutsideCurrentTemplate(
        int $schoolId,
        int $sessionId,
        array $departmentTemplateMapByClass
    ): void {
        if (!Schema::hasColumn('level_departments', 'is_template_active')) {
            return;
        }

        foreach ($departmentTemplateMapByClass as $level => $classRows) {
            $activeNames = collect(is_array($classRows) ? $classRows : [])
                ->flatMap(function ($row) {
                    if (!is_array($row) || !($row['enabled'] ?? false)) {
                        return [];
                    }

                    return $row['names'] ?? [];
                })
                ->map(fn ($name) => strtolower(trim((string) $name)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            DB::table('level_departments')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $sessionId)
                ->where('level', $level)
                ->get(['id', 'name'])
                ->each(function ($department) use ($activeNames) {
                    if (in_array(strtolower(trim((string) $department->name)), $activeNames, true)) {
                        return;
                    }

                    $this->markTemplateInactive('level_departments', (int) $department->id);
                });
        }
    }

    private function classHasRealUsage(int $classId): bool
    {
        foreach ([
            'class_students',
            'enrollments',
            'term_subjects',
            'term_attendance_settings',
            'student_attendances',
            'student_subject_exclusions',
            'student_behaviour_ratings',
        ] as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'class_id')
                && DB::table($table)->where('class_id', $classId)->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function deleteClassDepartmentIfUnused(int $schoolId, int $classId, string $name): void
    {
        $department = $this->findClassDepartment($schoolId, $classId, $name);
        if (!$department) {
            return;
        }

        if (
            Schema::hasTable('enrollments')
            && Schema::hasColumn('enrollments', 'department_id')
            && DB::table('enrollments')->where('department_id', (int) $department->id)->exists()
        ) {
            $this->markTemplateInactive('class_departments', (int) $department->id);
            return;
        }

        DB::table('class_departments')->where('id', (int) $department->id)->delete();
    }

    private function deleteLevelDepartmentIfUnused(int $schoolId, int $sessionId, string $level, string $name): void
    {
        $department = $this->findLevelDepartment($schoolId, $sessionId, $level, $name);
        if (!$department) {
            return;
        }

        $classIds = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('level', $level)
            ->pluck('id')
            ->all();

        $hasClassDepartment = !empty($classIds)
            && DB::table('class_departments')
                ->whereIn('class_id', $classIds)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
                ->exists();

        if (!$hasClassDepartment) {
            DB::table('level_departments')->where('id', (int) $department->id)->delete();
            return;
        }

        $this->markTemplateInactive('level_departments', (int) $department->id);
    }

    private function departmentNamesForTemplateClass(array $map, string $level, string $className): array
    {
        $rows = is_array($map[$level] ?? null) ? $map[$level] : [];
        $needle = strtolower(trim($className));

        foreach ($rows as $name => $row) {
            if (strtolower(trim((string) $name)) !== $needle || !is_array($row)) {
                continue;
            }

            if (!($row['enabled'] ?? false)) {
                return [];
            }

            return DepartmentTemplateSync::normalizeTemplateNames($row['names'] ?? []);
        }

        return [];
    }

    private function templateActiveValues(string $table, bool $active): array
    {
        return Schema::hasColumn($table, 'is_template_active')
            ? ['is_template_active' => $active]
            : [];
    }

    private function setModelTemplateActive(object $model, string $table, bool $active): void
    {
        if (Schema::hasColumn($table, 'is_template_active')) {
            $model->is_template_active = $active;
        }
    }

    private function markTemplateInactive(string $table, int $id): void
    {
        if (!Schema::hasColumn($table, 'is_template_active')) {
            return;
        }

        DB::table($table)->where('id', $id)->update([
            'is_template_active' => false,
            'updated_at' => now(),
        ]);
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

    private function deleteResidualSchoolRows(int $schoolId): void
    {
        foreach ($this->databaseTables() as $table) {
            if (in_array($table, ['schools', 'users'], true) || !Schema::hasColumn($table, 'school_id')) {
                continue;
            }

            try {
                DB::table($table)->where('school_id', $schoolId)->delete();
            } catch (\Throwable $e) {
                Log::warning('Residual school row cleanup skipped for table ' . $table, [
                    'school_id' => $schoolId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function databaseTables(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $databaseName = DB::getDatabaseName();
            return collect(DB::select('SHOW TABLES'))
                ->map(function ($row) use ($databaseName) {
                    $key = 'Tables_in_' . $databaseName;
                    return (string) ($row->{$key} ?? array_values((array) $row)[0] ?? '');
                })
                ->filter()
                ->values()
                ->all();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->map(fn ($row) => (string) $row->name)
                ->values()
                ->all();
        }

        return [];
    }

    private function deleteSchoolStorage(int $schoolId): void
    {
        try {
            Storage::disk('public')->deleteDirectory("schools/{$schoolId}");
            Storage::disk('local')->deleteDirectory("generated-documents/school-{$schoolId}");
        } catch (\Throwable $e) {
            Log::warning('School storage cleanup failed after deletion', [
                'school_id' => $schoolId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

