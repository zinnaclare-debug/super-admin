<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        // Create or get school
        $school = School::firstOrCreate(
            ['slug' => 'alpha-academy'],
            [
                'name' => 'Alpha Academy',
                'email' => 'admin@alpha-academy.com',
                'subdomain' => 'alpha',
                'username_prefix' => 'AA'
            ]
        );

        // Create test student user
        $student = User::firstOrCreate(
            ['email' => 'student@alpha.com'],
            [
                'name' => 'Test Student',
                'username' => 'AA-student1',
                'password' => Hash::make('password'),
                'role' => 'student',
                'school_id' => $school->id
            ]
        );

        // Create student profile if it doesn't exist
        Student::firstOrCreate(
            ['user_id' => $student->id],
            [
                'school_id' => $school->id,
                'sex' => 'M',
                'religion' => 'Christian',
                'dob' => '2005-01-15',
                'address' => '123 Student Street'
            ]
        );
    }
}
