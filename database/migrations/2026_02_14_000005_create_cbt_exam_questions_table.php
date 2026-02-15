<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (Schema::hasTable('cbt_exam_questions')) {
      return;
    }

    Schema::create('cbt_exam_questions', function (Blueprint $table) {
      $table->id();
      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('cbt_exam_id')->constrained('cbt_exams')->cascadeOnDelete();
      $table->foreignId('question_bank_question_id')->nullable()->constrained('question_bank_questions')->nullOnDelete();

      $table->text('question_text');
      $table->text('option_a');
      $table->text('option_b');
      $table->text('option_c')->nullable();
      $table->text('option_d')->nullable();
      $table->string('correct_option', 1);
      $table->text('explanation')->nullable();
      $table->string('media_path')->nullable();
      $table->string('media_type', 50)->nullable();
      $table->unsignedInteger('position')->default(0);

      $table->timestamps();

      $table->index(['school_id', 'cbt_exam_id']);
      $table->index(['cbt_exam_id', 'position']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cbt_exam_questions');
  }
};
