<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['classes', 'class_departments', 'level_departments'] as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'is_template_active')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_template_active')->default(true)->after('name');
            });
        }
    }

    public function down(): void
    {
        foreach (['classes', 'class_departments', 'level_departments'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'is_template_active')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_template_active');
            });
        }
    }
};
