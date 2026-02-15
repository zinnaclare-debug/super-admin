<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('class_activities', function (Blueprint $table) {
      $table->id();

      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

      // ties upload to an exact subject instance in a class+term (assigned teacher)
      $table->foreignId('term_subject_id')->constrained('term_subjects')->cascadeOnDelete();

      $table->string('title');
      $table->text('description')->nullable();

      $table->string('file_path');
      $table->string('original_name')->nullable();
      $table->string('mime_type')->nullable();
      $table->unsignedBigInteger('size')->nullable();

      $table->timestamps();

      $table->index(['school_id', 'term_subject_id']);
      $table->index(['school_id', 'uploaded_by_user_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('class_activities');
  }
};
