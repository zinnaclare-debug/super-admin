<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('results', function (Blueprint $table) {
      $table->id();
      $table->foreignId('school_id')->constrained()->cascadeOnDelete();

      // This ties the result to exactly ONE class+term+subject (and teacher)
      $table->foreignId('term_subject_id')->constrained('term_subjects')->cascadeOnDelete();

      $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

      $table->unsignedTinyInteger('ca')->default(0);     // 0-30
      $table->unsignedTinyInteger('exam')->default(0);   // 0-70

      $table->timestamps();

      $table->unique(['term_subject_id', 'student_id']);
      $table->index(['school_id', 'term_subject_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('results');
  }
};
