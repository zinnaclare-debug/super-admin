<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolAttendantSetting;
use App\Models\SchoolPublicHoliday;
use App\Models\StaffAttendantRecord;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    private function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): int
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
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

    private function dayContext(int $schoolId, SchoolAttendantSetting $setting): array
    {
        $timezone = $setting->timezone ?: 'Africa/Lagos';
        $now = Carbon::now($timezone);
        $date = $now->toDateString();
        $workingDays = $setting->working_days ?: $this->defaultWorkingDays();
        $isWorkingDay = in_array((int) $now->dayOfWeekIso, array_map('intval', $workingDays), true);
        $holiday = SchoolPublicHoliday::query()
            ->where('school_id', $schoolId)
            ->whereDate('holiday_date', $date)
            ->first();

        return [
            'now' => $now,
            'date' => $date,
            'is_working_day' => $isWorkingDay,
            'holiday' => $holiday,
            'is_blocked' => !$isWorkingDay || (bool) $holiday,
            'blocked_reason' => !$isWorkingDay ? 'Non-working day' : ($holiday ? $holiday->title : null),
        ];
    }

    public function today(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'staff', 403);

        $schoolId = (int) $user->school_id;
        $setting = $this->setting($schoolId);
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        $ctx = $this->dayContext($schoolId, $setting);
        $record = StaffAttendantRecord::query()
            ->where('school_id', $schoolId)
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->where('staff_user_id', $user->id)
            ->whereDate('attendance_date', $ctx['date'])
            ->first();

        return response()->json([
            'data' => [
                'today' => $ctx['date'],
                'server_time' => $ctx['now']->toIso8601String(),
                'current_session' => $session ? [
                    'id' => $session->id,
                    'label' => $session->session_name ?: $session->academic_year,
                ] : null,
                'current_term' => $term ? [
                    'id' => $term->id,
                    'name' => $term->name,
                ] : null,
                'is_working_day' => $ctx['is_working_day'],
                'is_blocked' => $ctx['is_blocked'],
                'blocked_reason' => $ctx['blocked_reason'],
                'holiday' => $ctx['holiday'] ? [
                    'title' => $ctx['holiday']->title,
                    'description' => $ctx['holiday']->description,
                ] : null,
                'setting' => [
                    'latitude' => $setting->latitude,
                    'longitude' => $setting->longitude,
                    'radius_meters' => (int) $setting->radius_meters,
                    'location_configured' => $setting->latitude !== null && $setting->longitude !== null,
                    'allow_outside_location' => (bool) $setting->allow_outside_location,
                    'sign_in_start_time' => $setting->sign_in_start_time,
                    'sign_in_end_time' => $setting->sign_in_end_time,
                    'late_after_time' => $setting->late_after_time,
                ],
                'record' => $record,
            ],
        ]);
    }

    public function signIn(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'staff', 403);

        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_meters' => 'nullable|integer|min:0|max:1000000',
            'device_info' => 'nullable|array',
        ]);

        $schoolId = (int) $user->school_id;
        $setting = $this->setting($schoolId);
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json(['message' => 'No current academic session/term configured for your school.'], 422);
        }

        $ctx = $this->dayContext($schoolId, $setting);

        if ($ctx['is_blocked']) {
            return response()->json([
                'message' => $ctx['blocked_reason'] ?: 'Attendant sign-in is not available today.',
            ], 422);
        }

        if ($setting->latitude === null || $setting->longitude === null) {
            return response()->json(['message' => 'School location has not been configured by school admin.'], 422);
        }

        $now = $ctx['now'];
        if ($setting->sign_in_start_time && $now->format('H:i:s') < $setting->sign_in_start_time) {
            return response()->json(['message' => 'Sign-in is not open yet.'], 422);
        }
        if ($setting->sign_in_end_time && $now->format('H:i:s') > $setting->sign_in_end_time) {
            return response()->json(['message' => 'Sign-in has closed for today.'], 422);
        }

        $distance = $this->distanceMeters(
            (float) $setting->latitude,
            (float) $setting->longitude,
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        $inside = $distance <= (int) $setting->radius_meters;

        $status = 'present';
        if (!$inside) {
            $status = 'out_of_range';
        } elseif ($setting->late_after_time && $now->format('H:i:s') > $setting->late_after_time) {
            $status = 'late';
        }

        $record = DB::transaction(function () use ($schoolId, $session, $term, $user, $ctx, $now, $data, $distance, $inside, $status, $request) {
            return StaffAttendantRecord::firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'staff_user_id' => $user->id,
                    'attendance_date' => $ctx['date'],
                ],
                [
                    'signed_in_at' => $now->copy()->timezone('UTC'),
                    'status' => $status,
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'accuracy_meters' => $data['accuracy_meters'] ?? null,
                    'distance_from_school_meters' => $distance,
                    'location_status' => $inside ? 'inside_school' : 'outside_school',
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'device_info' => $data['device_info'] ?? null,
                ]
            );
        });

        return response()->json([
            'message' => $record->wasRecentlyCreated ? 'Staff attendance signed successfully.' : 'You have already signed staff attendance today.',
            'data' => ['record' => $record],
        ]);
    }
}
