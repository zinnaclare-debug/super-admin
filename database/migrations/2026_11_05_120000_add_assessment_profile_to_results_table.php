<?php

use App\Support\AssessmentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->json('assessment_profile')->nullable()->after('classic_components');
        });

        // Freeze the legacy profile before schools opt in to a new template allocation.
        DB::table('results')
            ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
            ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->join('schools', 'schools.id', '=', 'results.school_id')
            ->whereNull('results.assessment_profile')
            ->orderBy('results.id')
            ->select(['results.id as result_id', 'classes.level as class_level', 'schools.assessment_schema'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $rawSchema = $row->assessment_schema;
                    if (is_string($rawSchema)) {
                        $decoded = json_decode($rawSchema, true);
                        $rawSchema = is_array($decoded) ? $decoded : null;
                    }

                    DB::table('results')
                        ->where('id', (int) $row->result_id)
                        ->update(['assessment_profile' => json_encode(AssessmentSchema::schemaForLevel($rawSchema, $row->class_level))]);
                }
            }, 'results.id', 'result_id');
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('assessment_profile');
        });
    }
};