<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolAttendantSetting;
use App\Models\SchoolPublicHoliday;
use App\Models\StaffAttendantRecord;
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

    public function context(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'school_admin', 403);

        $schoolId = (int) $user->school_id;
        $setting = $this->setting($schoolId);

        return response()->json([
            'data' => [
                'setting' => $setting,
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

        return response()->json(['message' => 'Attendant settings saved.', 'data' => ['setting' => $setting]]);
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
        ]);

        $query = StaffAttendantRecord::query()
            ->with(['staffUser:id,name,email,username'])
            ->where('school_id', $user->school_id)
            ->when(!empty($data['date_from']), fn ($q) => $q->whereDate('attendance_date', '>=', $data['date_from']))
            ->when(!empty($data['date_to']), fn ($q) => $q->whereDate('attendance_date', '<=', $data['date_to']))
            ->when(!empty($data['staff_user_id']), fn ($q) => $q->where('staff_user_id', $data['staff_user_id']))
            ->when(!empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->orderByDesc('attendance_date')
            ->orderByDesc('signed_in_at');

        $records = $query->paginate(50);

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
}
