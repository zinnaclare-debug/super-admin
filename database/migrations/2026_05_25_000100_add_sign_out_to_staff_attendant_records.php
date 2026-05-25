<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('staff_attendant_records')) {
            return;
        }

        Schema::table('staff_attendant_records', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_attendant_records', 'signed_out_at')) {
                $table->timestamp('signed_out_at')->nullable()->after('signed_in_at');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_latitude')) {
                $table->decimal('sign_out_latitude', 10, 7)->nullable()->after('location_status');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_longitude')) {
                $table->decimal('sign_out_longitude', 10, 7)->nullable()->after('sign_out_latitude');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_accuracy_meters')) {
                $table->unsignedInteger('sign_out_accuracy_meters')->nullable()->after('sign_out_longitude');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_distance_from_school_meters')) {
                $table->unsignedInteger('sign_out_distance_from_school_meters')->nullable()->after('sign_out_accuracy_meters');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_location_status')) {
                $table->string('sign_out_location_status')->nullable()->after('sign_out_distance_from_school_meters');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_ip_address')) {
                $table->string('sign_out_ip_address', 64)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_user_agent')) {
                $table->text('sign_out_user_agent')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('staff_attendant_records', 'sign_out_device_info')) {
                $table->json('sign_out_device_info')->nullable()->after('device_info');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('staff_attendant_records')) {
            return;
        }

        Schema::table('staff_attendant_records', function (Blueprint $table) {
            foreach ([
                'signed_out_at',
                'sign_out_latitude',
                'sign_out_longitude',
                'sign_out_accuracy_meters',
                'sign_out_distance_from_school_meters',
                'sign_out_location_status',
                'sign_out_ip_address',
                'sign_out_user_agent',
                'sign_out_device_info',
            ] as $column) {
                if (Schema::hasColumn('staff_attendant_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
