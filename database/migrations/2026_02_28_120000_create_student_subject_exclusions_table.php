<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_subject_exclusions')) {
            return;
        }

        Schema::create('student_subject_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_id', 'academic_session_id', 'class_id', 'subject_id', 'student_id'],
                'student_subject_exclusions_unique'
            );
            $table->index(['school_id', 'student_id'], 'student_subject_exclusions_school_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_exclusions');
    }
};

