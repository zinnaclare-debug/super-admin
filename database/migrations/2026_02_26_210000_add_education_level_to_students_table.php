<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('students', 'education_level')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('education_level')->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'education_level')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('education_level');
            });
        }
    }
};

