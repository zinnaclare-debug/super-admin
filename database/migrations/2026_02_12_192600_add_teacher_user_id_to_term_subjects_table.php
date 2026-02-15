<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/xxxx_add_teacher_user_id_to_term_subjects_table.php
public function up(): void
{
    Schema::table('term_subjects', function (Blueprint $table) {
        if (!Schema::hasColumn('term_subjects', 'teacher_user_id')) {
            $table->foreignId('teacher_user_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('users')
                ->nullOnDelete();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('term_subjects', function (Blueprint $table) {
            //
        });
    }
};
