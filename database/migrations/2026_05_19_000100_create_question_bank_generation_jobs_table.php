<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->text('prompt');
            $table->unsignedTinyInteger('question_count');
            $table->boolean('import_to_bank')->default(true);
            $table->unsignedSmallInteger('generated_count')->default(0);
            $table->unsignedSmallInteger('imported_count')->default(0);
            $table->json('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'teacher_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_generation_jobs');
    }
};
