<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserCredentialStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LoginDetailsController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'role' => ['nullable', Rule::in(['student', 'staff'])],
            'level' => ['nullable', 'string', 'max:60'],
            'department' => ['nullable', 'string', 'max:80'],
        ]);

        $result = $this->buildRows(
            $schoolId,
            $payload['role'] ?? null,
            $payload['level'] ?? null,
            $payload['department'] ?? null
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
        ]);

        $result = $this->buildRows(
            $schoolId,
            $payload['role'] ?? null,
            $payload['level'] ?? null,
            $payload['department'] ?? null
        );
        $rows = $result['rows'];
        $lines = [];
        $lines[] = $this->toCsvRow([
            'S/N',
            'Name',
            'Role',
            'Education Level',
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

    private function buildRows(
        int $schoolId,
        ?string $role = null,
        ?string $level = null,
        ?string $department = null
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
        if ($studentUserIds->isNotEmpty() && Schema::hasTable('students')) {
            $studentIdByUserId = DB::table('students')
                ->where('school_id', $schoolId)
                ->whereIn('user_id', $studentUserIds->all())
                ->pluck('id', 'user_id')
                ->mapWithKeys(fn ($studentId, $userId) => [(int) $userId => (int) $studentId])
                ->all();
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
                        'level' => strtolower(trim((string) ($row->class_level ?? ''))),
                        'department' => trim((string) ($row->department_name ?? '')),
                    ];
                })
                ->all();
        }

        $rawRows = $users->values()->map(function (User $user) use (
            $hasCredentialTable,
            $studentIdByUserId,
            $staffLevelByUserId,
            $studentProfileByStudentId
        ) {
            $credential = $hasCredentialTable ? $user->loginCredential : null;
            $password = UserCredentialStore::reveal($credential?->password_encrypted);
            $userId = (int) $user->id;

            $level = '';
            $department = '';
            if ((string) $user->role === 'staff') {
                $level = (string) ($staffLevelByUserId[$userId] ?? '');
            } elseif ((string) $user->role === 'student') {
                $studentId = (int) ($studentIdByUserId[$userId] ?? 0);
                $profile = $studentId ? ($studentProfileByStudentId[$studentId] ?? null) : null;
                $level = strtolower(trim((string) ($profile['level'] ?? '')));
                $department = trim((string) ($profile['department'] ?? ''));
            }

            return [
                'user_id' => $userId,
                'name' => (string) $user->name,
                'role' => (string) $user->role,
                'level' => $level,
                'department' => $department,
                'username' => (string) ($user->username ?? ''),
                'email' => (string) ($user->email ?? ''),
                'password' => $password ?? '',
                'last_password_set_at' => optional($credential?->last_password_set_at)->toDateTimeString(),
            ];
        });

        $levelFilter = strtolower(trim((string) $level));
        $departmentFilter = strtolower(trim((string) $department));

        $filteredRows = $rawRows
            ->filter(function (array $row) use ($levelFilter, $departmentFilter) {
                if ($levelFilter !== '' && strtolower((string) ($row['level'] ?? '')) !== $levelFilter) {
                    return false;
                }

                if ($departmentFilter !== '' && strtolower((string) ($row['department'] ?? '')) !== $departmentFilter) {
                    return false;
                }

                return true;
            })
            ->values()
            ->map(function (array $row, int $index) {
                $row['sn'] = $index + 1;
                return $row;
            })
            ->all();

        $levels = $rawRows
            ->pluck('level')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        $departments = $rawRows
            ->pluck('department')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => strtolower($value))
            ->values()
            ->all();

        return [
            'rows' => $filteredRows,
            'meta' => [
                'levels' => $levels,
                'departments' => $departments,
                'selected' => [
                    'role' => $role,
                    'level' => $levelFilter !== '' ? $levelFilter : null,
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
}
