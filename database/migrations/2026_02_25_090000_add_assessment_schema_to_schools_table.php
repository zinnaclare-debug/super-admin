<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'assessment_schema')) {
                $table->json('assessment_schema')->nullable()->after('head_signature_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'assessment_schema')) {
                $table->dropColumn('assessment_schema');
            }
        });
    }
};

