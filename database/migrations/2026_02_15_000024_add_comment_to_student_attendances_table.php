<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('student_attendances')) {
            return;
        }

        Schema::table('student_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('student_attendances', 'comment')) {
                $table->string('comment', 500)->nullable()->after('days_present');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('student_attendances')) {
            return;
        }

        Schema::table('student_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('student_attendances', 'comment')) {
                $table->dropColumn('comment');
            }
        });
    }
};

