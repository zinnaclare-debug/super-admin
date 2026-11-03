<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('results') && !Schema::hasColumn('results', 'classic_components')) {
            Schema::table('results', function (Blueprint $table) {
                $table->json('classic_components')->nullable()->after('teacher_comment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('results') && Schema::hasColumn('results', 'classic_components')) {
            Schema::table('results', function (Blueprint $table) {
                $table->dropColumn('classic_components');
            });
        }
    }
};
