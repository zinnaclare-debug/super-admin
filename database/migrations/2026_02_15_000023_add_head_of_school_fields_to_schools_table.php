<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'head_of_school_name')) {
                $table->string('head_of_school_name')->nullable()->after('logo_path');
            }

            if (!Schema::hasColumn('schools', 'head_signature_path')) {
                $table->string('head_signature_path')->nullable()->after('head_of_school_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'head_signature_path')) {
                $table->dropColumn('head_signature_path');
            }

            if (Schema::hasColumn('schools', 'head_of_school_name')) {
                $table->dropColumn('head_of_school_name');
            }
        });
    }
};

