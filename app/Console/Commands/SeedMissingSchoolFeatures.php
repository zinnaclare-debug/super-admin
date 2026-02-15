<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\School;
use App\Models\SchoolFeature;

class SeedMissingSchoolFeatures extends Command
{
    protected $signature = 'schools:seed-features';
    protected $description = 'Seed missing features for existing schools';

    public function handle()
    {
        $features = [
            ['key' => 'result', 'description' => 'Student results & grading'],
            ['key' => 'profile', 'description' => 'Student & staff profiles'],
            ['key' => 'topics', 'description' => 'Curriculum topics'],
            ['key' => 'e-library', 'description' => 'Digital library resources'],
            ['key' => 'class activities', 'description' => 'Assignments & activities'],
            ['key' => 'cbt', 'description' => 'Computer based tests'],
            ['key' => 'virtual class', 'description' => 'Online live classes'],
            ['key' => 'question bank', 'description' => 'Question repository'],
            ['key' => 'behaviour rating', 'description' => 'Student behaviour tracking'],
            ['key' => 'school fees', 'description' => 'Fees & payments'],
            ['key' => 'attendance', 'description' => 'Student attendance tracking'],
        ];

        $schools = School::all();

        foreach ($schools as $school) {
            foreach ($features as $feature) {
                SchoolFeature::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'feature' => $feature['key'],
                    ],
                    [
                        'description' => $feature['description'],
                        'enabled' => true,
                    ]
                );
            }
        }

        $this->info('✅ Missing features seeded successfully');
    }
}
