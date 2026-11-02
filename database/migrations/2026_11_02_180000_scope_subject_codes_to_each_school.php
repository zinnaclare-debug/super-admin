<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subjects') || !Schema::hasColumn('subjects', 'code')) {
            return;
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_code_unique');
            $table->unique(['school_id', 'code'], 'subjects_school_code_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('subjects') || !Schema::hasColumn('subjects', 'code')) {
            return;
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_school_code_unique');
            $table->unique('code', 'subjects_code_unique');
        });
    }
};
