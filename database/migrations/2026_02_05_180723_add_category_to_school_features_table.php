<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_features', function (Blueprint $table) {
            if (!Schema::hasColumn('school_features', 'category')) {
                $table->string('category')->default('general')->after('feature');
            }
        });
    }

    public function down(): void
    {
        Schema::table('school_features', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
