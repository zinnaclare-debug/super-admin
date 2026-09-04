<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_ai_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('staff_user_id');
            $table->unsignedBigInteger('academic_session_id');
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('term_subject_id');
            $table->unsignedBigInteger('subject_id');
            $table->text('topics');
            $table->unsignedTinyInteger('question_count')->default(10);
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('result_json')->nullable();
            $table->string('exam_questions_path')->nullable();
            $table->string('lesson_notes_path')->nullable();
            $table->string('lesson_plan_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'staff_user_id', 'academic_session_id', 'term_id'], 'teaching_ai_jobs_scope_idx');
            $table->index(['term_subject_id', 'status'], 'teaching_ai_jobs_subject_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_ai_generation_jobs');
    }
};
