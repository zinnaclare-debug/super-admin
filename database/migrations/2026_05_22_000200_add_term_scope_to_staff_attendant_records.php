<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('staff_attendant_records')) {
            return;
        }

        Schema::table('staff_attendant_records', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_attendant_records', 'academic_session_id')) {
                $table->foreignId('academic_session_id')
                    ->nullable()
                    ->after('school_id')
                    ->constrained('academic_sessions')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('staff_attendant_records', 'term_id')) {
                $table->foreignId('term_id')
                    ->nullable()
                    ->after('academic_session_id')
                    ->constrained('terms')
                    ->nullOnDelete();
            }
        });

        Schema::table('staff_attendant_records', function (Blueprint $table) {
            try {
                $table->dropUnique('staff_attendant_daily_unique');
            } catch (\Throwable $e) {
                // Some MySQL installs keep a different generated index name.
            }

            $table->unique(
                ['school_id', 'academic_session_id', 'term_id', 'staff_user_id', 'attendance_date'],
                'staff_attendant_term_daily_unique'
            );
            $table->index(['school_id', 'academic_session_id', 'term_id'], 'staff_attendant_period_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('staff_attendant_records')) {
            return;
        }

        Schema::table('staff_attendant_records', function (Blueprint $table) {
            try {
                $table->dropUnique('staff_attendant_term_daily_unique');
            } catch (\Throwable $e) {
                //
            }

            try {
                $table->dropIndex('staff_attendant_period_index');
            } catch (\Throwable $e) {
                //
            }

            $duplicates = DB::table('staff_attendant_records')
                ->select('school_id', 'staff_user_id', 'attendance_date', DB::raw('COUNT(*) as total'))
                ->groupBy('school_id', 'staff_user_id', 'attendance_date')
                ->having('total', '>', 1)
                ->exists();

            if (!$duplicates) {
                $table->unique(['school_id', 'staff_user_id', 'attendance_date'], 'staff_attendant_daily_unique');
            }

            if (Schema::hasColumn('staff_attendant_records', 'term_id')) {
                $table->dropConstrainedForeignId('term_id');
            }

            if (Schema::hasColumn('staff_attendant_records', 'academic_session_id')) {
                $table->dropConstrainedForeignId('academic_session_id');
            }
        });
    }
};
