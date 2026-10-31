<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('staff', 'education_levels')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->json('education_levels')->nullable()->after('education_level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff', 'education_levels')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('education_levels');
            });
        }
    }
};