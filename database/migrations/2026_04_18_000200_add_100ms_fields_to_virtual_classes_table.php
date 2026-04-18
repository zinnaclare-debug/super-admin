<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('virtual_classes', function (Blueprint $table) {
            $table->string('provider_room_id')->nullable()->after('provider');
            $table->string('staff_room_code', 32)->nullable()->after('provider_room_id');
            $table->string('student_room_code', 32)->nullable()->after('staff_room_code');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_classes', function (Blueprint $table) {
            $table->dropColumn([
                'provider_room_id',
                'staff_room_code',
                'student_room_code',
            ]);
        });
    }
};
