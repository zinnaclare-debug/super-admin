<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('virtual_classes', function (Blueprint $table) {
      $table->id();

      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('term_subject_id')->constrained('term_subjects')->cascadeOnDelete();

      $table->string('title');
      $table->text('description')->nullable();
      $table->string('meeting_link', 1000);
      $table->timestamp('starts_at')->nullable();

      $table->timestamps();

      $table->index(['school_id', 'term_subject_id']);
      $table->index(['school_id', 'uploaded_by_user_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('virtual_classes');
  }
};

