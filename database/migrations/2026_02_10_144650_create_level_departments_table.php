<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('level_departments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('school_id')->constrained()->onDelete('cascade');
      $table->foreignId('academic_session_id')->constrained()->onDelete('cascade');
      $table->string('level'); // nursery|primary|secondary
      $table->string('name');  // Science, Arts, A, B, etc.
      $table->timestamps();

      $table->unique(['school_id','academic_session_id','level','name'], 'level_depts_unique');
    });
  }

  public function down(): void {
    Schema::dropIfExists('level_departments');
  }
};
