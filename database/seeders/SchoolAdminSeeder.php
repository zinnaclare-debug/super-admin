<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\School;

class SchoolAdminSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'alpha-academy'],
            [
                'name' => 'Alpha Academy',
                'subdomain' => 'alpha'
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@alpha.com'],
            [
                'name' => 'Alpha Admin',
                'password' => bcrypt('password'),
                'role' => 'school_admin',
                'school_id' => $school->id
            ]
        );
    }
}
