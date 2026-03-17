<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('enrollments', ['class_id', 'term_id'], 'enrollments_class_term_idx');
        $this->addIndexIfMissing('enrollments', ['student_id', 'term_id'], 'enrollments_student_term_idx');
        $this->addIndexIfMissing('enrollments', ['department_id', 'term_id'], 'enrollments_department_term_idx');

        $this->addIndexIfMissing('class_students', ['school_id', 'academic_session_id', 'class_id'], 'class_students_school_session_class_idx');
        $this->addIndexIfMissing('class_students', ['school_id', 'student_id'], 'class_students_school_student_idx');

        $this->addIndexIfMissing('results', ['school_id', 'student_id'], 'results_school_student_idx');
        $this->addIndexIfMissing('results', ['student_id', 'term_subject_id'], 'results_student_term_subject_idx');

        if (Schema::hasColumn('term_subjects', 'school_id')) {
            $this->addIndexIfMissing('term_subjects', ['school_id', 'class_id', 'term_id'], 'term_subjects_school_class_term_idx');
        }

        if (Schema::hasColumn('term_subjects', 'teacher_user_id')) {
            $columns = Schema::hasColumn('term_subjects', 'school_id')
                ? ['school_id', 'teacher_user_id', 'term_id']
                : ['teacher_user_id', 'term_id'];

            $name = Schema::hasColumn('term_subjects', 'school_id')
                ? 'term_subjects_school_teacher_term_idx'
                : 'term_subjects_teacher_term_idx';

            $this->addIndexIfMissing('term_subjects', $columns, $name);
        }

        $this->addIndexIfMissing(
            'student_subject_exclusions',
            ['school_id', 'academic_session_id', 'class_id', 'subject_id'],
            'student_subject_exclusions_scope_idx'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('enrollments', 'enrollments_class_term_idx');
        $this->dropIndexIfExists('enrollments', 'enrollments_student_term_idx');
        $this->dropIndexIfExists('enrollments', 'enrollments_department_term_idx');

        $this->dropIndexIfExists('class_students', 'class_students_school_session_class_idx');
        $this->dropIndexIfExists('class_students', 'class_students_school_student_idx');

        $this->dropIndexIfExists('results', 'results_school_student_idx');
        $this->dropIndexIfExists('results', 'results_student_term_subject_idx');

        $this->dropIndexIfExists('term_subjects', 'term_subjects_school_class_term_idx');
        $this->dropIndexIfExists('term_subjects', 'term_subjects_school_teacher_term_idx');
        $this->dropIndexIfExists('term_subjects', 'term_subjects_teacher_term_idx');

        $this->dropIndexIfExists('student_subject_exclusions', 'student_subject_exclusions_scope_idx');
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($columns, $name) {
            $tableBlueprint->index($columns, $name);
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($name) {
            $tableBlueprint->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

        return !empty($indexes);
    }
};
