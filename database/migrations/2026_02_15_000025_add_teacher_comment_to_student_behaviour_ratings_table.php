<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('student_behaviour_ratings')) {
            return;
        }

        Schema::table('student_behaviour_ratings', function (Blueprint $table) {
            if (!Schema::hasColumn('student_behaviour_ratings', 'teacher_comment')) {
                $table->string('teacher_comment', 500)->nullable()->after('self_control');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('student_behaviour_ratings')) {
            return;
        }

        Schema::table('student_behaviour_ratings', function (Blueprint $table) {
            if (Schema::hasColumn('student_behaviour_ratings', 'teacher_comment')) {
                $table->dropColumn('teacher_comment');
            }
        });
    }
};

