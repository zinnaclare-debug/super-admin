<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\School;
use App\Models\SchoolFeature;

class FixSchoolFeaturesSeeder extends Seeder
{
    public function run()
    {
        $features = [
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

        $schools = School::all();

        foreach ($schools as $school) {
            foreach ($features as $feature) {
                SchoolFeature::updateOrCreate([
                    'school_id' => $school->id,
                    'feature' => $feature,
                ], [
                    'enabled' => true,
                ]);
            }
        }

        $this->command->info('School features fixed for '.count($schools).' schools.');
    }
}
