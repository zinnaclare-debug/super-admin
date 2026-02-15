<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('term_subjects', 'teacher_user_id')) {
            Schema::table('term_subjects', function (Blueprint $table) {
                $table->foreignId('teacher_user_id')
                    ->nullable()
                    ->after('subject_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('term_subjects', 'teacher_user_id')) {
            return;
        }

        Schema::table('term_subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_user_id');
        });
    }
};
