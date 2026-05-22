<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('school_attendant_settings')) {
            Schema::create('school_attendant_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('radius_meters')->default(150);
                $table->string('timezone')->default('Africa/Lagos');
                $table->json('working_days')->nullable();
                $table->time('sign_in_start_time')->nullable();
                $table->time('sign_in_end_time')->nullable();
                $table->time('late_after_time')->nullable();
                $table->boolean('allow_outside_location')->default(false);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('school_id', 'school_attendant_settings_school_unique');
            });
        }

        if (!Schema::hasTable('school_public_holidays')) {
            Schema::create('school_public_holidays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->date('holiday_date');
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'holiday_date'], 'school_public_holidays_unique');
                $table->index(['school_id', 'holiday_date']);
            });
        }

        if (!Schema::hasTable('staff_attendant_records')) {
            Schema::create('staff_attendant_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->timestamp('signed_in_at')->nullable();
                $table->string('status')->default('present');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('accuracy_meters')->nullable();
                $table->unsignedInteger('distance_from_school_meters')->nullable();
                $table->string('location_status')->default('unknown');
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('device_info')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();

                $table->unique(['school_id', 'staff_user_id', 'attendance_date'], 'staff_attendant_daily_unique');
                $table->index(['school_id', 'attendance_date']);
                $table->index(['school_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendant_records');
        Schema::dropIfExists('school_public_holidays');
        Schema::dropIfExists('school_attendant_settings');
    }
};
