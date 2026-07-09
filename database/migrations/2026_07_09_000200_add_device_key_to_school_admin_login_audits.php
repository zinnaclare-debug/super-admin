<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('school_admin_login_audits')
            && !Schema::hasColumn('school_admin_login_audits', 'device_key')
        ) {
            Schema::table('school_admin_login_audits', function (Blueprint $table) {
                $table->string('device_key', 64)->nullable()->after('personal_access_token_id');
                $table->index(['user_id', 'device_key'], 'school_admin_login_user_device_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('school_admin_login_audits') && Schema::hasColumn('school_admin_login_audits', 'device_key')) {
            Schema::table('school_admin_login_audits', function (Blueprint $table) {
                $table->dropIndex('school_admin_login_user_device_idx');
                $table->dropColumn('device_key');
            });
        }
    }
};
