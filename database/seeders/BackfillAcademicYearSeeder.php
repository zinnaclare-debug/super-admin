<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackfillAcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement("UPDATE academic_sessions SET academic_year = REPLACE(session_name, '/', '-') WHERE academic_year IS NULL OR academic_year = ''");
    }
}
