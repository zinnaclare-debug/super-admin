<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('schools') && !Schema::hasColumn('schools', 'comment_only_schema')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->json('comment_only_schema')->nullable()->after('behaviour_schema');
            });
        }

        if (Schema::hasTable('student_behaviour_ratings') && !Schema::hasColumn('student_behaviour_ratings', 'learning_observations')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->json('learning_observations')->nullable()->after('ratings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_behaviour_ratings') && Schema::hasColumn('student_behaviour_ratings', 'learning_observations')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->dropColumn('learning_observations');
            });
        }

        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'comment_only_schema')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('comment_only_schema');
            });
        }
    }
};