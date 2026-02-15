<?php

use App\Models\School;
use App\Models\SchoolFeature;

if (! function_exists('school')) {
    function school()
    {
        if (auth()->check()) {
            return auth()->user()->school;
        }

        return null;
    }
}

if (! function_exists('schoolFeatureEnabled')) {
    function schoolFeatureEnabled(string $feature): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super admin bypass
        if ($user->role === 'super_admin') {
            return true;
        }

        return SchoolFeature::where('school_id', $user->school_id)
            ->where('feature', $feature)
            ->where('enabled', true)
            ->exists();
    }
}

