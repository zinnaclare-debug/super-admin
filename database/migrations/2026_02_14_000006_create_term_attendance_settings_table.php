<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (Schema::hasTable('term_attendance_settings')) {
      return;
    }

    Schema::create('term_attendance_settings', function (Blueprint $table) {
      $table->id();
      $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
      $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
      $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
      $table->unsignedInteger('total_school_days')->default(0);
      $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();

      $table->unique(['school_id', 'class_id', 'term_id'], 'att_setting_unique');
      $table->index(['school_id', 'class_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('term_attendance_settings');
  }
};

