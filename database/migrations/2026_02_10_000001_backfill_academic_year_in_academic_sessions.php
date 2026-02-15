<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Backfill academic_year from session_name where academic_year is null
        // For sessions like "2025/2026", the academic_year becomes "2025/2026"
        DB::table('academic_sessions')
            ->whereNull('academic_year')
            ->update([
                'academic_year' => DB::raw('session_name')
            ]);
    }

    public function down(): void
    {
        // Set academic_year back to null
        DB::table('academic_sessions')
            ->update(['academic_year' => null]);
    }
};
