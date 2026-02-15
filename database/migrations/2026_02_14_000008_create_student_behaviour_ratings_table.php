<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (Schema::hasTable('student_behaviour_ratings')) {
      return;
    }

    Schema::create('student_behaviour_ratings', function (Blueprint $table) {
      $table->id();
      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
      $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
      $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

      // 1-5 scale ratings
      $table->unsignedTinyInteger('handwriting')->default(0);
      $table->unsignedTinyInteger('speech')->default(0);
      $table->unsignedTinyInteger('attitude')->default(0);
      $table->unsignedTinyInteger('reading')->default(0);
      $table->unsignedTinyInteger('punctuality')->default(0);
      $table->unsignedTinyInteger('teamwork')->default(0);
      $table->unsignedTinyInteger('self_control')->default(0);

      $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();

      $table->unique(['school_id', 'class_id', 'term_id', 'student_id'], 'student_behaviour_unique');
      $table->index(['school_id', 'class_id', 'term_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('student_behaviour_ratings');
  }
};

