<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('term_subjects', function (Blueprint $table) {
      $table->foreignId('school_id')->after('id')->constrained()->cascadeOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('term_subjects', function (Blueprint $table) {
      $table->dropConstrainedForeignId('school_id');
    });
  }
};
