<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'status')) {
                $table->string('status', 24)->default('active')->after('photo_path');
            }

            if (!Schema::hasColumn('students', 'graduated_at')) {
                $table->timestamp('graduated_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('students', 'graduation_session_id')) {
                $table->foreignId('graduation_session_id')
                    ->nullable()
                    ->after('graduated_at')
                    ->constrained('academic_sessions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'graduation_session_id')) {
                $table->dropConstrainedForeignId('graduation_session_id');
            }

            if (Schema::hasColumn('students', 'graduated_at')) {
                $table->dropColumn('graduated_at');
            }

            if (Schema::hasColumn('students', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
