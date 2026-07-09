<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schools') && !Schema::hasColumn('schools', 'school_admin_login_limit')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->unsignedSmallInteger('school_admin_login_limit')->default(2)->after('status');
            });
        }

        if (
            Schema::hasTable('school_admin_login_audits')
            && !Schema::hasColumn('school_admin_login_audits', 'personal_access_token_id')
        ) {
            Schema::table('school_admin_login_audits', function (Blueprint $table) {
                $table->unsignedBigInteger('personal_access_token_id')->nullable()->after('user_id');
                $table->index('personal_access_token_id', 'school_admin_login_token_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('school_admin_login_audits') && Schema::hasColumn('school_admin_login_audits', 'personal_access_token_id')) {
            Schema::table('school_admin_login_audits', function (Blueprint $table) {
                $table->dropIndex('school_admin_login_token_idx');
                $table->dropColumn('personal_access_token_id');
            });
        }

        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'school_admin_login_limit')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('school_admin_login_limit');
            });
        }
    }
};
