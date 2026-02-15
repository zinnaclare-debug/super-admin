<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('topic_materials', function (Blueprint $table) {
      $table->id();

      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();

      // This is the "assigned subject in a term + class"
      $table->foreignId('term_subject_id')->constrained('term_subjects')->cascadeOnDelete();

      $table->string('title')->nullable();
      $table->string('file_path');
      $table->string('original_name')->nullable();
      $table->string('mime_type')->nullable();
      $table->unsignedBigInteger('size')->nullable();

      $table->timestamps();

      $table->index(['school_id', 'teacher_user_id']);
      $table->index(['school_id', 'term_subject_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('topic_materials');
  }
};
