<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (!Schema::hasTable('cbt_exams')) {
      return;
    }

    Schema::table('cbt_exams', function (Blueprint $table) {
      if (!Schema::hasColumn('cbt_exams', 'teacher_user_id')) {
        $table->foreignId('teacher_user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
      }
      if (!Schema::hasColumn('cbt_exams', 'term_subject_id')) {
        $table->foreignId('term_subject_id')->nullable()->after('teacher_user_id')->constrained('term_subjects')->nullOnDelete();
      }
      if (!Schema::hasColumn('cbt_exams', 'instructions')) {
        $table->text('instructions')->nullable()->after('title');
      }
      if (!Schema::hasColumn('cbt_exams', 'starts_at')) {
        $table->timestamp('starts_at')->nullable()->after('instructions');
      }
      if (!Schema::hasColumn('cbt_exams', 'ends_at')) {
        $table->timestamp('ends_at')->nullable()->after('starts_at');
      }
      if (!Schema::hasColumn('cbt_exams', 'duration_minutes')) {
        $table->unsignedInteger('duration_minutes')->nullable()->after('ends_at');
      }
      if (!Schema::hasColumn('cbt_exams', 'security_policy')) {
        $table->json('security_policy')->nullable()->after('status');
      }
    });

    // Backfill duration_minutes from legacy duration column if present.
    if (Schema::hasColumn('cbt_exams', 'duration') && Schema::hasColumn('cbt_exams', 'duration_minutes')) {
      DB::statement('UPDATE cbt_exams SET duration_minutes = COALESCE(duration_minutes, duration, 60)');
    } elseif (Schema::hasColumn('cbt_exams', 'duration_minutes')) {
      DB::statement('UPDATE cbt_exams SET duration_minutes = COALESCE(duration_minutes, 60)');
    }
  }

  public function down(): void
  {
    // Intentionally non-destructive for compatibility migrations.
  }
};

