<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('term_attendance_settings')) {
            return;
        }

        Schema::table('term_attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('term_attendance_settings', 'next_term_begin_date')) {
                $table->date('next_term_begin_date')->nullable()->after('total_school_days');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('term_attendance_settings')) {
            return;
        }

        Schema::table('term_attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('term_attendance_settings', 'next_term_begin_date')) {
                $table->dropColumn('next_term_begin_date');
            }
        });
    }
};

