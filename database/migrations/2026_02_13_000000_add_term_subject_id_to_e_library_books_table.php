<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('e_library_books', function (Blueprint $table) {
      $table->foreignId('term_subject_id')
        ->nullable()
        ->after('uploaded_by_user_id')
        ->constrained('term_subjects')
        ->nullOnDelete();

      $table->index(['school_id', 'term_subject_id']);
    });
  }

  public function down(): void
  {
    Schema::table('e_library_books', function (Blueprint $table) {
      $table->dropForeign(['term_subject_id']);
      $table->dropColumn('term_subject_id');
    });
  }
};
