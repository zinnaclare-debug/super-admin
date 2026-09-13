<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'exit_reason')) {
                $table->string('exit_reason', 32)->nullable()->index()->after('status');
            }
            if (!Schema::hasColumn('students', 'reactivation_requested_at')) {
                $table->timestamp('reactivation_requested_at')->nullable()->after('exit_reason');
            }
            if (!Schema::hasColumn('students', 'reactivation_requested_by_user_id')) {
                $table->unsignedBigInteger('reactivation_requested_by_user_id')->nullable()->after('reactivation_requested_at');
            }
            if (!Schema::hasColumn('students', 'reactivation_approved_at')) {
                $table->timestamp('reactivation_approved_at')->nullable()->after('reactivation_requested_by_user_id');
            }
            if (!Schema::hasColumn('students', 'reactivation_approved_by_user_id')) {
                $table->unsignedBigInteger('reactivation_approved_by_user_id')->nullable()->after('reactivation_approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach (['reactivation_approved_by_user_id', 'reactivation_approved_at', 'reactivation_requested_by_user_id', 'reactivation_requested_at', 'exit_reason'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};