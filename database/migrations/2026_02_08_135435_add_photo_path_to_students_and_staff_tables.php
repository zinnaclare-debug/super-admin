<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('address');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
