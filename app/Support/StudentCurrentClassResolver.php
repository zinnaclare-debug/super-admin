<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\TermSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentCurrentClassResolver
{
    public static function forCurrentTerm(int $schoolId, int $sessionId, int $termId, int $studentId): array
    {
        $candidateClassIds = [];

        $enrollmentQuery = Enrollment::query()
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->where('classes.school_id', $schoolId)
            ->where('classes.academic_session_id', $sessionId)
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.term_id', $termId)
            ->orderByDesc('enrollments.id');
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollmentQuery->where('enrollments.school_id', $schoolId);
        }

        $activeClassId = $enrollmentQuery->value('enrollments.class_id');
        if ($activeClassId) {
            $candidateClassIds[] = (int) $activeClassId;
        }

        if (Schema::hasTable('class_students')) {
            $classStudentQuery = DB::table('class_students')
                ->join('classes', 'classes.id', '=', 'class_students.class_id')
                ->where('class_students.student_id', $studentId)
                ->where('classes.school_id', $schoolId)
                ->where('classes.academic_session_id', $sessionId)
                ->orderByDesc('class_students.id');

            if (Schema::hasColumn('class_students', 'school_id')) {
                $classStudentQuery->where('class_students.school_id', $schoolId);
            }
            if (Schema::hasColumn('class_students', 'academic_session_id')) {
                $classStudentQuery->where('class_students.academic_session_id', $sessionId);
            }

            $candidateClassIds = array_merge($candidateClassIds, $classStudentQuery
                ->pluck('class_students.class_id')
                ->map(fn ($id) => (int) $id)
                ->all());
        }

        // A student may be promoted into a pending session before staff briefly
        // reactivate the previous session to correct published scores. Saved
        // result rows provide a scoped recovery link for that exact term.
        if (Schema::hasTable('results')) {
            $resultClassIds = DB::table('results')
                ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
                ->where('results.school_id', $schoolId)
                ->where('results.student_id', $studentId)
                ->where('term_subjects.term_id', $termId)
                ->when(Schema::hasColumn('term_subjects', 'school_id'), function ($query) use ($schoolId) {
                    $query->where('term_subjects.school_id', $schoolId);
                })
                ->orderByDesc('results.id')
                ->pluck('term_subjects.class_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $candidateClassIds = array_merge($candidateClassIds, $resultClassIds);
        }

        $candidateClassIds = array_values(array_unique(array_filter($candidateClassIds, fn ($id) => $id > 0)));
        if (empty($candidateClassIds)) {
            return [];
        }

        $classIdsWithSubjects = TermSubject::query()
            ->where('school_id', $schoolId)
            ->where('term_id', $termId)
            ->whereIn('class_id', $candidateClassIds)
            ->orderByDesc('id')
            ->pluck('class_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        foreach ($candidateClassIds as $classId) {
            if (in_array($classId, $classIdsWithSubjects, true)) {
                return [$classId];
            }
        }

        return [(int) $candidateClassIds[0]];
    }
}