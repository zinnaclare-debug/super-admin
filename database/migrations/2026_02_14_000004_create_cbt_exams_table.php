<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (Schema::hasTable('cbt_exams')) {
      return;
    }

    Schema::create('cbt_exams', function (Blueprint $table) {
      $table->id();
      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('term_subject_id')->constrained('term_subjects')->cascadeOnDelete();

      $table->string('title');
      $table->text('instructions')->nullable();
      $table->timestamp('starts_at');
      $table->timestamp('ends_at');
      $table->unsignedInteger('duration_minutes')->default(60);
      $table->string('status', 20)->default('draft'); // draft|published|closed
      $table->json('security_policy')->nullable();

      $table->timestamps();

      $table->index(['school_id', 'teacher_user_id']);
      $table->index(['school_id', 'term_subject_id']);
      $table->index(['school_id', 'starts_at', 'ends_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cbt_exams');
  }
};
