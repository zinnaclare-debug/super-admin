<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolAttendantSetting;
use App\Models\SchoolPublicHoliday;
use App\Models\StaffAttendantRecord;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;

class AttendantController extends Controller
{
    private function defaultWorkingDays(): array
    {
        return [1, 2, 3, 4, 5];
    }

    private function setting(int $schoolId): SchoolAttendantSetting
    {
        return SchoolAttendantSetting::firstOrCreate(
            ['school_id' => $schoolId],
            [
                'timezone' => 'Africa/Lagos',
                'working_days' => $this->defaultWorkingDays(),
                'radius_meters' => 150,
                'allow_outside_location' => true,
            ]
        );
    }

    private function resolveCurrentSessionAndTerm(int $schoolId): array
    {
        $session = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return [null, null];
        }

        $term = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('is_current', true)
            ->first();

        if (!$term) {
            $term = Term::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->orderBy('id')
                ->first();
        }

        return [$session, $term];
    }

    public function context(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        $schoolId = (int) $user->school_id;
        $setting = $this->setting($schoolId);
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);

        return response()->json([
            'data' => [
                'setting' => $setting,
                'current_session' => $session ? [
                    'id' => $session->id,
                    'label' => $session->session_name ?: $session->academic_year,
                ] : null,
                'current_term' => $term ? [
                    'id' => $term->id,
                    'name' => $term->name,
                ] : null,
                'staff' => User::query()
                    ->where('school_id', $schoolId)
                    ->where('role', 'staff')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'username']),
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        $data = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:20|max:5000',
            'timezone' => 'nullable|string|max:80',
            'working_days' => 'nullable|array',
            'working_days.*' => 'integer|min:1|max:7',
            'sign_in_start_time' => 'nullable|date_format:H:i',
            'sign_in_end_time' => 'nullable|date_format:H:i',
            'late_after_time' => 'nullable|date_format:H:i',
            'allow_outside_location' => 'boolean',
        ]);

        $setting = $this->setting((int) $user->school_id);
        $setting->fill([
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'radius_meters' => $data['radius_meters'],
            'timezone' => $data['timezone'] ?: 'Africa/Lagos',
            'working_days' => array_values(array_unique(array_map('intval', $data['working_days'] ?? $this->defaultWorkingDays()))),
            'sign_in_start_time' => $data['sign_in_start_time'] ?? null,
            'sign_in_end_time' => $data['sign_in_end_time'] ?? null,
            'late_after_time' => $data['late_after_time'] ?? null,
            'allow_outside_location' => (bool) ($data['allow_outside_location'] ?? false),
            'updated_by_user_id' => $user->id,
        ]);
        if (!$setting->created_by_user_id) {
            $setting->created_by_user_id = $user->id;
        }
        $setting->save();

        return response()->json(['message' => 'Staff attendance settings saved.', 'data' => ['setting' => $setting]]);
    }

    public function holidays(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        return response()->json([
            'data' => SchoolPublicHoliday::query()
                ->where('school_id', $user->school_id)
                ->orderByDesc('holiday_date')
                ->limit(80)
                ->get(),
        ]);
    }

    public function storeHoliday(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        $data = $request->validate([
            'holiday_date' => 'required|date',
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:1000',
        ]);

        $holiday = SchoolPublicHoliday::updateOrCreate(
            ['school_id' => $user->school_id, 'holiday_date' => $data['holiday_date']],
            [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'created_by_user_id' => $user->id,
            ]
        );

        return response()->json(['message' => 'Holiday saved.', 'data' => $holiday]);
    }

    public function destroyHoliday(Request $request, SchoolPublicHoliday $holiday)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin' && (int) $holiday->school_id === (int) $user->school_id, 403);

        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted.']);
    }

    public function records(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'staff_user_id' => 'nullable|integer',
            'status' => 'nullable|string|max:60',
            'academic_session_id' => 'nullable|integer',
            'term_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        [$session, $term] = $this->resolveCurrentSessionAndTerm((int) $user->school_id);
        $sessionId = (int) ($data['academic_session_id'] ?? ($session?->id ?? 0));
        $termId = (int) ($data['term_id'] ?? ($term?->id ?? 0));

        $query = StaffAttendantRecord::query()
            ->with(['staffUser:id,name,email,username', 'academicSession:id,session_name,academic_year', 'term:id,name'])
            ->where('school_id', $user->school_id)
            ->when($sessionId > 0, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->when($termId > 0, fn ($q) => $q->where('term_id', $termId))
            ->when(!empty($data['date_from']), fn ($q) => $q->whereDate('attendance_date', '>=', $data['date_from']))
            ->when(!empty($data['date_to']), fn ($q) => $q->whereDate('attendance_date', '<=', $data['date_to']))
            ->when(!empty($data['staff_user_id']), fn ($q) => $q->where('staff_user_id', $data['staff_user_id']))
            ->when(!empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->orderByDesc('attendance_date')
            ->orderByDesc('signed_in_at');

        $records = $query->paginate((int) ($data['per_page'] ?? 100));

        return response()->json([
            'data' => [
                'records' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ],
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        $schoolId = (int) $user->school_id;
        $staff = User::query()
            ->where('school_id', $schoolId)
            ->where('role', 'staff')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'username', 'is_active', 'created_at']);

        $records = StaffAttendantRecord::query()
            ->with(['staffUser:id,name,email,username,is_active', 'academicSession:id,session_name,academic_year,status', 'term:id,name,academic_session_id'])
            ->where('school_id', $schoolId)
            ->whereNotNull('academic_session_id')
            ->whereNotNull('term_id')
            ->orderBy('attendance_date')
            ->get();

        $recordStaff = $records
            ->pluck('staffUser')
            ->filter()
            ->unique('id')
            ->values();
        $staffRows = $staff->merge($recordStaff)
            ->unique('id')
            ->sortBy(fn ($item) => strtolower((string) $item->name))
            ->values();

        $sessions = AcademicSession::query()
            ->with(['terms' => fn ($query) => $query->orderBy('id')])
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->get();
        $recordsByTerm = $records->groupBy(fn ($record) => (int) $record->term_id);

        $history = $sessions->map(function (AcademicSession $session) use ($recordsByTerm, $staffRows) {
            $terms = $session->terms->map(function (Term $term) use ($recordsByTerm, $staffRows) {
                $termRecords = $recordsByTerm->get((int) $term->id, collect());
                $expectedDates = $termRecords
                    ->pluck('attendance_date')
                    ->map(fn ($date) => optional($date)->toDateString() ?: (string) $date)
                    ->filter()
                    ->unique()
                    ->values();
                $expectedDays = $expectedDates->count();
                $recordsByStaff = $termRecords->groupBy(fn ($record) => (int) $record->staff_user_id);

                $summary = $staffRows->map(function (User $staff, int $index) use ($recordsByStaff, $expectedDays) {
                    $rows = $recordsByStaff->get((int) $staff->id, collect());
                    $present = $rows
                        ->pluck('attendance_date')
                        ->map(fn ($date) => optional($date)->toDateString() ?: (string) $date)
                        ->filter()
                        ->unique()
                        ->count();
                    $late = $rows
                        ->filter(fn ($row) => $row->status === 'late')
                        ->pluck('attendance_date')
                        ->map(fn ($date) => optional($date)->toDateString() ?: (string) $date)
                        ->unique()
                        ->count();
                    $farFromSchool = $rows
                        ->filter(fn ($row) => $row->status === 'out_of_range' || $row->location_status === 'outside_school')
                        ->pluck('attendance_date')
                        ->map(fn ($date) => optional($date)->toDateString() ?: (string) $date)
                        ->unique()
                        ->count();
                    $absent = max(0, $expectedDays - $present);

                    return [
                        'sn' => $index + 1,
                        'staff_user_id' => $staff->id,
                        'staff_name' => $staff->name,
                        'present' => $present,
                        'late' => $late,
                        'far_from_school_present' => $farFromSchool,
                        'absent' => $absent,
                        'expected_days' => $expectedDays,
                        'attendance_percent' => $expectedDays > 0 ? round(($present / $expectedDays) * 100, 1) : null,
                        'last_sign_in' => optional($rows->sortByDesc('signed_in_at')->first())->signed_in_at,
                    ];
                })->values();

                return [
                    'id' => $term->id,
                    'name' => $term->name,
                    'expected_days' => $expectedDays,
                    'recorded_dates' => $expectedDates,
                    'summary' => $summary,
                ];
            })->values();

            return [
                'id' => $session->id,
                'label' => $session->session_name ?: $session->academic_year ?: ('Session ' . $session->id),
                'status' => $session->status,
                'terms' => $terms,
            ];
        })->values();

        return response()->json(['data' => ['sessions' => $history]]);
    }
}
