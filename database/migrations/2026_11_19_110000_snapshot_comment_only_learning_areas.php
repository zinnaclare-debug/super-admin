<?php

use App\Models\School;
use App\Support\CommentOnlySchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_behaviour_ratings')) {
            return;
        }

        if (! Schema::hasColumn('student_behaviour_ratings', 'learning_area_schema')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->json('learning_area_schema')->nullable()->after('learning_observations');
            });
        }

        School::query()->select(['id', 'comment_only_schema'])->orderBy('id')->each(function (School $school): void {
            $schema = CommentOnlySchema::normalize($school->comment_only_schema);

            DB::table('student_behaviour_ratings')
                ->where('school_id', (int) $school->id)
                ->whereNull('learning_area_schema')
                ->whereNotNull('learning_observations')
                ->update(['learning_area_schema' => json_encode($schema)]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('student_behaviour_ratings') && Schema::hasColumn('student_behaviour_ratings', 'learning_area_schema')) {
            Schema::table('student_behaviour_ratings', function (Blueprint $table) {
                $table->dropColumn('learning_area_schema');
            });
        }
    }
};