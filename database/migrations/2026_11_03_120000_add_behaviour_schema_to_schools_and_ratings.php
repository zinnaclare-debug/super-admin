<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('schools') && !Schema::hasColumn('schools', 'behaviour_schema')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->json('behaviour_schema')->nullable()->after('result_template_config');
            });
        }

        if (Schema::hasTable('student_behaviour_ratings') && !Schema::hasColumn('student_behaviour_ratings', 'ratings')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->json('ratings')->nullable()->after('self_control');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_behaviour_ratings') && Schema::hasColumn('student_behaviour_ratings', 'ratings')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->dropColumn('ratings');
            });
        }

        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'behaviour_schema')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('behaviour_schema');
            });
        }
    }
};
