<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fee_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->json('line_items')->nullable();
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->foreignId('configured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_id', 'student_id', 'academic_session_id', 'term_id'],
                'student_fee_plan_unique_scope'
            );
            $table->index(['school_id', 'academic_session_id', 'term_id'], 'student_fee_plan_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_plans');
    }
};

