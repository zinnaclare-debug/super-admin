<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FeatureAccessController extends Controller
{
    private function enabledSchoolFeatures(Request $request): array
    {
        $school = $request->user()?->school;

        if (!$school) return [];

        // from DB (super admin controls enabled/disabled)
        $enabled = $school->features()
            ->where('enabled', true)
            ->pluck('feature')
            ->toArray();

        return $enabled;
    }

    public function staffFeatures(Request $request)
    {
        $enabled = $this->enabledSchoolFeatures($request);
        $allowed = config('role_features.staff', []);

        \Log::info('Staff features check', [
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->role,
            'school_id' => $request->user()?->school_id,
            'enabled_count' => count($enabled),
            'allowed_count' => count($allowed),
        ]);

        $final = array_values(array_intersect($enabled, $allowed));

        return response()->json(['data' => $final], 200);
    }

    public function studentFeatures(Request $request)
    {
        $enabled = $this->enabledSchoolFeatures($request);
        $allowed = config('role_features.student', []);
        $school = $request->user()?->school;

        \Log::info('Student features check', [
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->role,
            'school_id' => $request->user()?->school_id,
            'enabled_count' => count($enabled),
            'allowed_count' => count($allowed),
        ]);

        $final = array_values(array_intersect($enabled, $allowed));

        // Keep Subjects visible when any subject-dependent feature is enabled.
        if (!in_array('subjects', $final, true)) {
            $subjectDependent = ['topics', 'e-library', 'class activities', 'virtual class', 'cbt'];
            $hasDependent = count(array_intersect($final, $subjectDependent)) > 0;
            if ($hasDependent && in_array('subjects', $allowed, true)) {
                $final[] = 'subjects';
            }
        }

        if ($school && !$school->results_published) {
            $final = array_values(array_filter($final, fn ($feature) => $feature !== 'results'));
        }

        return response()->json(['data' => $final], 200);
    }
}
