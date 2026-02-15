<?php

namespace App\Observers;

use App\Models\School;
use App\Models\SchoolFeature;

class SchoolObserver
{
    /**
     * Handle the School "created" event.
     */
    public function created(School $school): void
    {
        $defaultFeatures = [
            // GENERAL
            'attendance',
            'results',
            'profile',
            'topics',
            'e-library',
            'class activities',
            'cbt',
            'virtual class',
            'question bank',
            'behaviour rating',
            'school fees',

            // SCHOOL ADMIN
            'register',
            'users',
            'academics',
            'academic_session',
            'promotion',
            'broadsheet',
            'transcript',
            'teacher_report',
            'student_report',
        ];

        foreach ($defaultFeatures as $feature) {
            SchoolFeature::create([
                'school_id' => $school->id,
                'feature'   => $feature,
                'enabled'   => true,
            ]);
        }
    }
}
