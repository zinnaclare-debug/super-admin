<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('e_library_books', function (Blueprint $table) {
      $table->id();

      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

      // optional filters
      $table->string('education_level')->nullable(); // nursery|primary|secondary
      $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();

      $table->string('title');
      $table->string('author')->nullable();
      $table->string('description')->nullable();

      $table->string('file_path');
      $table->string('original_name')->nullable();
      $table->string('mime_type')->nullable();
      $table->unsignedBigInteger('size')->nullable();

      $table->timestamps();

      $table->index(['school_id', 'education_level']);
      $table->index(['school_id', 'subject_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('e_library_books');
  }
};
