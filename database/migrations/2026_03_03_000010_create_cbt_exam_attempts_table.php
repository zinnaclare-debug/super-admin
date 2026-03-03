<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cbt_exam_attempts')) {
            return;
        }

        Schema::create('cbt_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('cbt_exam_id')->constrained('cbt_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // in_progress | submitted | exited | time_up | disqualified
            $table->string('status', 24)->default('in_progress');
            // manual | auto | exit
            $table->string('submit_mode', 16)->nullable();
            $table->json('answers')->nullable();

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('attempted')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->unsignedInteger('wrong')->default(0);
            $table->unsignedInteger('unanswered')->default(0);
            $table->decimal('score_percent', 6, 2)->default(0);

            $table->unsignedInteger('security_warnings')->default(0);
            $table->unsignedInteger('head_movement_warnings')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'cbt_exam_id', 'student_id'], 'cbt_exam_attempts_unique_student_exam');
            $table->index(['school_id', 'student_id']);
            $table->index(['cbt_exam_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_exam_attempts');
    }
};

