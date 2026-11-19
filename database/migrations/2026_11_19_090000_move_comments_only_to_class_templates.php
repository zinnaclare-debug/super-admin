<?php

use App\Models\School;
use App\Support\AssessmentSchema;
use App\Support\ClassTemplateSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        School::query()->select(['id', 'class_templates', 'assessment_schema'])->orderBy('id')->each(function (School $school): void {
            $templates = ClassTemplateSchema::normalize($school->class_templates);
            $changed = false;

            foreach ($templates as &$section) {
                $wasCommentsOnly = AssessmentSchema::commentsOnlyForLevel(
                    $school->assessment_schema,
                    (string) ($section['key'] ?? '')
                );

                foreach (($section['classes'] ?? []) as &$classRow) {
                    // The former level-wide option applies to every class in that level once.
                    $classRow['comments_only'] = $wasCommentsOnly;
                    $changed = true;
                }
                unset($classRow);
            }
            unset($section);

            if ($changed) {
                $school->class_templates = $templates;
                $school->save();
            }
        });
    }

    public function down(): void
    {
        // Class-level choices intentionally remain when rolling back this migration.
    }
};