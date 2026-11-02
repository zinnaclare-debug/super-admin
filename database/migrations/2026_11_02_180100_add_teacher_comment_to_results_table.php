<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('results') && !Schema::hasColumn('results', 'teacher_comment')) {
            Schema::table('results', function (Blueprint $table) {
                $table->text('teacher_comment')->nullable()->after('exam');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('results') && Schema::hasColumn('results', 'teacher_comment')) {
            Schema::table('results', function (Blueprint $table) {
                $table->dropColumn('teacher_comment');
            });
        }
    }
};
