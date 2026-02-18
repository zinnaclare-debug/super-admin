<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'lytebridgeprofessionalservices@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678=TEN'),
                'role' => User::ROLE_SUPER_ADMIN,
                'school_id' => null,
            ]
        );

        $user->is_active = true;
        $user->save();
    }
}
