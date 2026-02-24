<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE academic_sessions
            MODIFY status ENUM('pending', 'current', 'completed')
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE academic_sessions
            SET status = 'completed'
            WHERE status = 'pending'
        ");

        DB::statement("
            ALTER TABLE academic_sessions
            MODIFY status ENUM('current', 'completed')
            NOT NULL DEFAULT 'completed'
        ");
    }
};
