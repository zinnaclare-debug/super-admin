<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('question_bank_questions', function (Blueprint $table) {
      $table->id();
      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();

      $table->text('question_text');
      $table->text('option_a');
      $table->text('option_b');
      $table->text('option_c')->nullable();
      $table->text('option_d')->nullable();
      $table->string('correct_option', 1); // A/B/C/D
      $table->text('explanation')->nullable();
      $table->string('source_type', 20)->default('manual'); // manual|ai

      $table->string('media_path')->nullable();
      $table->string('media_type', 50)->nullable(); // image|video|formula

      $table->timestamps();

      $table->index(['school_id', 'teacher_user_id']);
      $table->index(['school_id', 'subject_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('question_bank_questions');
  }
};

