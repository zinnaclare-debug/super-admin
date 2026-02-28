<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Support\ClassTemplateSchema;
use App\Support\UserCredentialStore;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class LoginDetailsController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'role' => ['nullable', Rule::in(['student', 'staff'])],
            'level' => ['nullable', 'string', 'max:60'],
            'department' => ['nullable', 'string', 'max:80'],
            'class_id' => ['nullable', 'integer'],
        ]);

        $result = $this->buildRows(
            $schoolId,
            $payload['role'] ?? null,
            $payload['level'] ?? null,
            $payload['department'] ?? null,
            isset($payload['class_id']) ? (int) $payload['class_id'] : null
        );

        return response()->json([
            'data' => $result['rows'],
            'meta' => $result['meta'],
        ]);
    }

    public function download(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'role' => ['nullable', Rule::in(['student', 'staff'])],
            'level' => ['nullable', 'string', 'max:60'],
            'department' => ['nullable', 'string', 'max:80'],
            'class_id' => ['nullable', 'integer'],
        ]);

        $result = $this->buildRows(
            $schoolId,
            $payload['role'] ?? null,
            $payload['level'] ?? null,
            $payload['department'] ?? null,
            isset($payload['class_id']) ? (int) $payload['class_id'] : null
        );
        $rows = $result['rows'];
        $lines = [];
        $lines[] = $this->toCsvRow([
            'S/N',
            'Name',
            'Role',
            'Education Level',
            'Class',
            'Department',
            'Username',
            'Email',
            'Password',
            'Last Password Set',
        ]);

        foreach ($rows as $row) {
            $lines[] = $this->toCsvRow([
                $row['sn'],
                $row['name'],
                $row['role'],
                $row['level'],
                $row['class_name'],
                $row['department'],
                $row['username'],
                $row['email'],
                $row['password'],
                $row['last_password_set_at'],
            ]);
        }

        $fileName = 'user_login_details_' . now()->format('Ymd_His') . '.csv';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'role' => ['nullable', Rule::in(['student', 'staff'])],
            'level' => ['nullable', 'string', 'max:60'],
            'department' => ['nullable', 'string', 'max:80'],
            'class_id' => ['nullable', 'integer'],
        ]);

        $result = $this->buildRows(
            $schoolId,
            $payload['role'] ?? null,
            $payload['level'] ?? null,
            $payload['department'] ?? null,
            isset($payload['class_id']) ? (int) $payload['class_id'] : null
        );
        $rows = $result['rows'] ?? [];
        $school = School::query()->find($schoolId);

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.user_login_details', [
                'school' => $school,
                'rows' => $rows,
                'filters' => $result['meta']['selected'] ?? [],
                'generatedAt' => now(),
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $fileName = 'user_login_details_' . now()->format('Ymd_His') . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to generate PDF download.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buildRows(
        int $schoolId,
        ?string $role = null,
        ?string $level = null,
        ?string $department = null,
        ?int $classId = null
    ): array
    {
        $hasCredentialTable = Schema::hasTable('user_login_credentials');

        $users = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('role', ['student', 'staff'])
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($hasCredentialTable, fn ($q) => $q->with('loginCredential'))
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $studentUserIds = $users
            ->where('role', 'student')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $studentIdByUserId = [];
        $studentLevelByUserId = [];
        if ($studentUserIds->isNotEmpty() && Schema::hasTable('students')) {
            $hasStudentEducationLevel = Schema::hasColumn('students', 'education_level');

            $studentQuery = DB::table('students')
                ->where('school_id', $schoolId)
                ->whereIn('user_id', $studentUserIds->all())
                ->select(['id', 'user_id']);
            if ($hasStudentEducationLevel) {
                $studentQuery->addSelect('education_level');
            }
            $studentRows = $studentQuery->get();

            $studentIdByUserId = $studentRows
                ->mapWithKeys(fn ($row) => [(int) $row->user_id => (int) $row->id])
                ->all();

            if ($hasStudentEducationLevel) {
                $studentLevelByUserId = $studentRows
                    ->mapWithKeys(function ($row) {
                        return [(int) $row->user_id => strtolower(trim((string) ($row->education_level ?? '')))];
                    })
                    ->all();
            }
        }

        $staffLevelByUserId = [];
        if (Schema::hasTable('staff')) {
            $staffLevelByUserId = DB::table('staff')
                ->where('school_id', $schoolId)
                ->whereIn('user_id', $users->pluck('id')->all())
                ->pluck('education_level', 'user_id')
                ->mapWithKeys(fn ($value, $userId) => [(int) $userId => strtolower(trim((string) $value))])
                ->all();
        }
        $staffUserIds = $users
            ->where('role', 'staff')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $currentSessionId = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->value('id');
        $currentTermId = $currentSessionId
            ? DB::table('terms')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $currentSessionId)
                ->where('is_current', true)
                ->value('id')
            : null;
        [$staffTeachingClassesByUserId, $staffDepartmentAssignmentsByUserId] = $this->resolveStaffAssignments(
            $schoolId,
            $staffUserIds,
            $currentSessionId ? (int) $currentSessionId : null,
            $currentTermId ? (int) $currentTermId : null
        );

        $studentProfileByStudentId = [];
        $studentIds = collect(array_values($studentIdByUserId))->filter()->values();
        if (
            $studentIds->isNotEmpty()
            && Schema::hasTable('enrollments')
            && Schema::hasTable('classes')
        ) {
            $enrollmentQuery = DB::table('enrollments')
                ->join('classes', 'classes.id', '=', 'enrollments.class_id')
                ->leftJoin('class_departments', 'class_departments.id', '=', 'enrollments.department_id')
                ->where('classes.school_id', $schoolId)
                ->whereIn('enrollments.student_id', $studentIds->all())
                ->orderByDesc('enrollments.id')
                ->select([
                    'enrollments.student_id',
                    'classes.id as class_id',
                    'classes.name as class_name',
                    'classes.level as class_level',
                    'class_departments.name as department_name',
                    'enrollments.term_id',
                    'classes.academic_session_id',
                ]);

            if (Schema::hasColumn('enrollments', 'school_id')) {
                $enrollmentQuery->where('enrollments.school_id', $schoolId);
            }
            if ($currentSessionId) {
                $enrollmentQuery->where('classes.academic_session_id', (int) $currentSessionId);
            }
            if ($currentTermId) {
                $enrollmentQuery->where('enrollments.term_id', (int) $currentTermId);
            }

            $studentProfileByStudentId = $enrollmentQuery
                ->get()
                ->groupBy('student_id')
                ->map(function ($rows) {
                    $row = $rows->first();
                    return [
                        'class_id' => !empty($row->class_id) ? (int) $row->class_id : null,
                        'class_name' => trim((string) ($row->class_name ?? '')),
                        'level' => strtolower(trim((string) ($row->class_level ?? ''))),
                        'department' => trim((string) ($row->department_name ?? '')),
                    ];
                })
                ->all();
        }

        $rawRows = $users->values()->map(function (User $user) use (
            $hasCredentialTable,
            $studentIdByUserId,
            $studentLevelByUserId,
            $staffLevelByUserId,
            $studentProfileByStudentId,
            $staffTeachingClassesByUserId,
            $staffDepartmentAssignmentsByUserId
        ) {
            $credential = $hasCredentialTable ? $user->loginCredential : null;
            $password = UserCredentialStore::reveal($credential?->password_encrypted);
            $userId = (int) $user->id;

            $level = '';
            $classId = null;
            $className = '';
            $department = '';
            if ((string) $user->role === 'staff') {
                $level = $this->normalizeLevelValue((string) ($staffLevelByUserId[$userId] ?? ''));
                $className = (string) ($staffTeachingClassesByUserId[$userId] ?? '');
                $department = (string) ($staffDepartmentAssignmentsByUserId[$userId] ?? '');
            } elseif ((string) $user->role === 'student') {
                $studentId = (int) ($studentIdByUserId[$userId] ?? 0);
                $profile = $studentId ? ($studentProfileByStudentId[$studentId] ?? null) : null;
                $classId = isset($profile['class_id']) ? (int) $profile['class_id'] : null;
                $className = trim((string) ($profile['class_name'] ?? ''));
                $studentEducationLevel = $this->normalizeLevelValue((string) ($studentLevelByUserId[$userId] ?? ''));
                $profileLevel = $this->normalizeLevelValue((string) ($profile['level'] ?? ''));
                $level = $studentEducationLevel !== '' ? $studentEducationLevel : $profileLevel;
                $department = trim((string) ($profile['department'] ?? ''));
            }

            return [
                'user_id' => $userId,
                'name' => (string) $user->name,
                'role' => (string) $user->role,
                'level' => $level,
                'class_id' => $classId,
                'class_name' => $className,
                'department' => $department,
                'username' => (string) ($user->username ?? ''),
                'email' => (string) ($user->email ?? ''),
                'password' => $password ?? '',
                'last_password_set_at' => optional($credential?->last_password_set_at)->toDateTimeString(),
            ];
        });

        $levelFilter = $this->normalizeLevelValue((string) $level);
        $departmentFilter = strtolower(trim((string) $department));
        $classFilter = (int) ($classId ?? 0);

        $classOptionsQuery = DB::table('classes')
            ->where('school_id', $schoolId)
            ->orderBy('level')
            ->orderBy('name')
            ->select(['id', 'name', 'level']);

        if ($currentSessionId) {
            $classOptionsQuery->where('academic_session_id', (int) $currentSessionId);
        }
        if ($levelFilter !== '') {
            $classOptionsQuery->whereRaw('LOWER(classes.level) LIKE ?', [$levelFilter . '%']);
        }

        $classOptions = $classOptionsQuery
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => trim((string) ($row->name ?? '')),
                    'level' => $this->normalizeLevelValue((string) ($row->level ?? '')),
                ];
            })
            ->values()
            ->all();

        $validClassId = null;
        if ($classFilter > 0) {
            foreach ($classOptions as $item) {
                if ((int) ($item['id'] ?? 0) === $classFilter) {
                    $validClassId = $classFilter;
                    break;
                }
            }
        }

        $studentScopedFiltersActive = $validClassId !== null || $departmentFilter !== '';

        $filteredRows = $rawRows
            ->filter(function (array $row) use (
                $levelFilter,
                $departmentFilter,
                $validClassId,
                $role,
                $studentScopedFiltersActive
            ) {
                if ($levelFilter !== '' && $this->normalizeLevelValue((string) ($row['level'] ?? '')) !== $levelFilter) {
                    return false;
                }

                $isStudent = (string) ($row['role'] ?? '') === 'student';
                if ($studentScopedFiltersActive && $role !== 'staff' && !$isStudent) {
                    return false;
                }

                if ($isStudent) {
                    if ($validClassId !== null && (int) ($row['class_id'] ?? 0) !== $validClassId) {
                        return false;
                    }

                    if ($departmentFilter !== '' && strtolower((string) ($row['department'] ?? '')) !== $departmentFilter) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->map(function (array $row, int $index) {
                $row['sn'] = $index + 1;
                return $row;
            })
            ->all();

        $templateLevels = [];
        $school = School::query()->find($schoolId);
        if ($school) {
            $templateLevels = ClassTemplateSchema::activeLevelKeys(
                ClassTemplateSchema::normalize($school->class_templates)
            );
        }

        $levels = collect($templateLevels)
            ->merge(
                collect($classOptions)
                    ->pluck('level')
                    ->all()
            )
            ->merge(
                collect($rawRows)
                    ->pluck('level')
                    ->all()
            )
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->map(fn ($value) => $this->normalizeLevelValue((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $departmentRows = collect($rawRows)
            ->filter(fn ($row) => (string) ($row['role'] ?? '') === 'student');

        if ($levelFilter !== '') {
            $departmentRows = $departmentRows
                ->filter(fn ($row) => $this->normalizeLevelValue((string) ($row['level'] ?? '')) === $levelFilter);
        }
        if ($validClassId !== null) {
            $departmentRows = $departmentRows
                ->filter(fn ($row) => (int) ($row['class_id'] ?? 0) === $validClassId);
        }

        $departments = $departmentRows
            ->pluck('department')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => strtolower($value))
            ->values()
            ->all();

        if ($role === 'staff') {
            $classOptions = [];
            $departments = [];
            $validClassId = null;
            $departmentFilter = '';
        }

        return [
            'rows' => $filteredRows,
            'meta' => [
                'levels' => $levels,
                'classes' => $classOptions,
                'departments' => $departments,
                'selected' => [
                    'role' => $role,
                    'level' => $levelFilter !== '' ? $levelFilter : null,
                    'class_id' => $validClassId,
                    'department' => $departmentFilter !== '' ? $departmentFilter : null,
                ],
            ],
        ];
    }

    private function toCsvRow(array $columns): string
    {
        $escaped = array_map(function ($value) {
            $text = (string) ($value ?? '');
            $text = str_replace('"', '""', $text);
            return '"' . $text . '"';
        }, $columns);

        return implode(',', $escaped);
    }

    private function resolveStaffAssignments(
        int $schoolId,
        array $staffUserIds,
        ?int $currentSessionId,
        ?int $currentTermId
    ): array {
        if (empty($staffUserIds) || !Schema::hasTable('classes')) {
            return [[], []];
        }

        $classSetsByUserId = [];
        $departmentSetsByUserId = [];

        if (Schema::hasTable('term_subjects') && Schema::hasColumn('term_subjects', 'teacher_user_id')) {
            $teachingClassesQuery = DB::table('term_subjects')
                ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
                ->whereNotNull('term_subjects.teacher_user_id')
                ->whereIn('term_subjects.teacher_user_id', $staffUserIds)
                ->select([
                    'term_subjects.teacher_user_id',
                    'classes.name as class_name',
                ]);

            if (Schema::hasColumn('term_subjects', 'school_id')) {
                $teachingClassesQuery->where('term_subjects.school_id', $schoolId);
            } else {
                $teachingClassesQuery->where('classes.school_id', $schoolId);
            }
            if ($currentSessionId) {
                $teachingClassesQuery->where('classes.academic_session_id', $currentSessionId);
            }
            if ($currentTermId) {
                $teachingClassesQuery->where('term_subjects.term_id', $currentTermId);
            }

            foreach ($teachingClassesQuery->get() as $row) {
                $this->addStaffAssignmentValue(
                    $classSetsByUserId,
                    (int) ($row->teacher_user_id ?? 0),
                    (string) ($row->class_name ?? '')
                );
            }
        }

        if (Schema::hasColumn('classes', 'class_teacher_user_id')) {
            $classTeacherQuery = DB::table('classes')
                ->where('classes.school_id', $schoolId)
                ->whereNotNull('classes.class_teacher_user_id')
                ->whereIn('classes.class_teacher_user_id', $staffUserIds)
                ->select([
                    'classes.class_teacher_user_id',
                    'classes.name as class_name',
                ]);

            if ($currentSessionId) {
                $classTeacherQuery->where('classes.academic_session_id', $currentSessionId);
            }

            foreach ($classTeacherQuery->get() as $row) {
                $this->addStaffAssignmentValue(
                    $classSetsByUserId,
                    (int) ($row->class_teacher_user_id ?? 0),
                    (string) ($row->class_name ?? '')
                );
            }
        }

        if (Schema::hasTable('class_departments') && Schema::hasColumn('class_departments', 'class_teacher_user_id')) {
            $departmentQuery = DB::table('class_departments')
                ->join('classes', 'classes.id', '=', 'class_departments.class_id')
                ->where('classes.school_id', $schoolId)
                ->whereNotNull('class_departments.class_teacher_user_id')
                ->whereIn('class_departments.class_teacher_user_id', $staffUserIds)
                ->select([
                    'class_departments.class_teacher_user_id',
                    'classes.name as class_name',
                    'class_departments.name as department_name',
                ]);

            if (Schema::hasColumn('class_departments', 'school_id')) {
                $departmentQuery->where('class_departments.school_id', $schoolId);
            }
            if ($currentSessionId) {
                $departmentQuery->where('classes.academic_session_id', $currentSessionId);
            }

            foreach ($departmentQuery->get() as $row) {
                $userId = (int) ($row->class_teacher_user_id ?? 0);
                $className = trim((string) ($row->class_name ?? ''));
                $departmentName = trim((string) ($row->department_name ?? ''));

                $this->addStaffAssignmentValue($classSetsByUserId, $userId, $className);

                if ($departmentName !== '') {
                    $assignment = $className !== ''
                        ? trim($className . ' ' . $departmentName)
                        : $departmentName;
                    $this->addStaffAssignmentValue($departmentSetsByUserId, $userId, $assignment);
                }
            }
        }

        return [
            $this->compileStaffAssignmentValues($classSetsByUserId),
            $this->compileStaffAssignmentValues($departmentSetsByUserId),
        ];
    }

    private function addStaffAssignmentValue(array &$setMap, int $userId, string $value): void
    {
        $cleaned = trim($value);
        if ($userId < 1 || $cleaned === '') {
            return;
        }

        if (!isset($setMap[$userId])) {
            $setMap[$userId] = [];
        }

        $setMap[$userId][strtolower($cleaned)] = $cleaned;
    }

    private function compileStaffAssignmentValues(array $setMap): array
    {
        $compiled = [];

        foreach ($setMap as $userId => $valuesByKey) {
            $values = array_values($valuesByKey);
            natcasesort($values);
            $compiled[(int) $userId] = implode(', ', $values);
        }

        return $compiled;
    }

    private function normalizeLevelValue(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? '';
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'pre_nursery') || str_starts_with($normalized, 'prenursery')) {
            return 'pre_nursery';
        }
        if (str_starts_with($normalized, 'nursery')) {
            return 'nursery';
        }
        if (str_starts_with($normalized, 'primary')) {
            return 'primary';
        }
        if (str_starts_with($normalized, 'secondary')) {
            return 'secondary';
        }

        return $normalized;
    }
}
