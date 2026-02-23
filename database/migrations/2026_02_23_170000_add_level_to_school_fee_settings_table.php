<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_fee_settings', function (Blueprint $table) {
            $table->dropUnique('school_fee_setting_unique');
            $table->string('level')->nullable()->after('term_id');
            $table->unique(
                ['school_id', 'academic_session_id', 'term_id', 'level'],
                'school_fee_setting_level_unique'
            );
            $table->index(['school_id', 'term_id', 'level'], 'school_fee_setting_level_idx');
        });
    }

    public function down(): void
    {
        Schema::table('school_fee_settings', function (Blueprint $table) {
            $table->dropIndex('school_fee_setting_level_idx');
            $table->dropUnique('school_fee_setting_level_unique');
            $table->dropColumn('level');
            $table->unique(
                ['school_id', 'academic_session_id', 'term_id'],
                'school_fee_setting_unique'
            );
        });
    }
};

