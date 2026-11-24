<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('schools') && !Schema::hasColumn('schools', 'behaviour_rating_format')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('behaviour_rating_format', 10)->default('number')->after('behaviour_schema');
            });
        }

        if (Schema::hasTable('student_behaviour_ratings') && !Schema::hasColumn('student_behaviour_ratings', 'behaviour_rating_format')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->string('behaviour_rating_format', 10)->default('number')->after('ratings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_behaviour_ratings') && Schema::hasColumn('student_behaviour_ratings', 'behaviour_rating_format')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->dropColumn('behaviour_rating_format');
            });
        }

        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'behaviour_rating_format')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('behaviour_rating_format');
            });
        }
    }
};
