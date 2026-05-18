<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('teaching_materials', 'term_subject_id')) {
                $table->foreignId('term_subject_id')
                    ->nullable()
                    ->after('term_id')
                    ->constrained('term_subjects')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('teaching_materials', 'subject_id')) {
                $table->foreignId('subject_id')
                    ->nullable()
                    ->after('term_subject_id')
                    ->constrained('subjects')
                    ->nullOnDelete();
            }

            $table->index(
                ['school_id', 'staff_user_id', 'academic_session_id', 'term_id', 'term_subject_id'],
                'teaching_materials_staff_subject_period_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('teaching_materials', function (Blueprint $table) {
            $table->dropIndex('teaching_materials_staff_subject_period_idx');

            if (Schema::hasColumn('teaching_materials', 'subject_id')) {
                $table->dropConstrainedForeignId('subject_id');
            }

            if (Schema::hasColumn('teaching_materials', 'term_subject_id')) {
                $table->dropConstrainedForeignId('term_subject_id');
            }
        });
    }
};
