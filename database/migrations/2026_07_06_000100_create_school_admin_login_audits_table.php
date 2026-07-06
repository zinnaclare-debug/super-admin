<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_admin_login_audits')) {
            return;
        }

        Schema::create('school_admin_login_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 64)->nullable();
            $table->string('forwarded_ip', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('device_info')->nullable();
            $table->string('device_type', 40)->nullable();
            $table->string('device_model', 120)->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('platform', 80)->nullable();
            $table->string('pc_name', 120)->nullable();
            $table->string('location_label', 255)->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'logged_in_at'], 'school_admin_login_school_date_idx');
            $table->index(['user_id', 'logged_in_at'], 'school_admin_login_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_admin_login_audits');
    }
};
