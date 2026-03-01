<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\ELibraryBook;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ELibraryController extends Controller
{
    private function resolveCurrentTermId(int $schoolId, int $sessionId): ?int
    {
        if (Schema::hasColumn('terms', 'is_current')) {
            $current = Term::where('school_id', $schoolId)
                ->where('academic_session_id', $sessionId)
                ->where('is_current', true)
                ->first();
            if ($current) return (int)$current->id;
        }

        $fallback = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->orderBy('id')
            ->first();

        return $fallback ? (int)$fallback->id : null;
    }

    private function currentSessionClassIds(int $schoolId, int $sessionId, int $studentId, int $currentTermId): array
    {
        $enrollQuery = Enrollment::query()
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->where('classes.school_id', $schoolId)
            ->where('classes.academic_session_id', $sessionId)
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.term_id', $currentTermId)
            ->orderByDesc('enrollments.id');
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollQuery->where('enrollments.school_id', $schoolId);
        }

        $activeClassId = $enrollQuery->value('enrollments.class_id');
        if ($activeClassId) {
            return [(int) $activeClassId];
        }

        $classIds = [];
        if (Schema::hasTable('class_students')) {
            $classStudentQuery = DB::table('class_students');
            if (Schema::hasColumn('class_students', 'school_id')) {
                $classStudentQuery->where('school_id', $schoolId);
            }
            if (Schema::hasColumn('class_students', 'academic_session_id')) {
                $classStudentQuery->where('academic_session_id', $sessionId);
            }
            if (Schema::hasColumn('class_students', 'student_id')) {
                $classStudentQuery->where('student_id', $studentId);
            }

            $classIds = $classStudentQuery
                ->pluck('class_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        if (!empty($classIds)) {
            return [(int) $classIds[0]];
        }

        $legacyQuery = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('term_id', $currentTermId)
            ->orderByDesc('id');
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $legacyQuery->where('school_id', $schoolId);
        }

        $legacyClassId = $legacyQuery->value('class_id');
        return $legacyClassId ? [(int) $legacyClassId] : [];
    }

    // GET /api/student/e-library/subjects
    // show subjects this student is assigned to (based on enrollments in CURRENT session)
    public function mySubjects(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        $schoolId = $user->school_id;

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) return response()->json(['data' => []]);

        $currentTermId = $this->resolveCurrentTermId((int)$schoolId, (int)$session->id);
        if (!$currentTermId) return response()->json(['data' => []]);

        // ✅ IMPORTANT: enrollments.student_id references students.id (not users.id)
        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) return response()->json(['data' => []]);

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id, $currentTermId);
        if (empty($classIds)) return response()->json(['data' => []]);

        $termSubjects = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.term_id', $currentTermId)
            ->whereIn('term_subjects.class_id', $classIds)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->when(Schema::hasTable('student_subject_exclusions'), function ($query) use ($schoolId, $session, $student) {
                $query->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $session, $student) {
                    $join->on('student_subject_exclusions.class_id', '=', 'term_subjects.class_id')
                        ->on('student_subject_exclusions.subject_id', '=', 'term_subjects.subject_id')
                        ->where('student_subject_exclusions.school_id', '=', $schoolId)
                        ->where('student_subject_exclusions.academic_session_id', '=', (int) $session->id)
                        ->where('student_subject_exclusions.student_id', '=', (int) $student->id);
                })
                ->whereNull('student_subject_exclusions.id');
            })
            ->orderBy('subjects.name')
            ->get([
                'term_subjects.id as term_subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
            ])
            ->unique('term_subject_id')
            ->values();

        return response()->json(['data' => $termSubjects]);
    }

    // GET /api/student/e-library?term_subject_id=123
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        $schoolId = $user->school_id;

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) return response()->json(['data' => []]);

        $currentTermId = $this->resolveCurrentTermId((int)$schoolId, (int)$session->id);
        if (!$currentTermId) return response()->json(['data' => []]);

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) return response()->json(['data' => []]);

        $filterTermSubjectId = $request->query('term_subject_id');

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id, $currentTermId);
        if (empty($classIds)) return response()->json(['data' => []]);

        $allowedTermSubjects = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.term_id', $currentTermId)
            ->whereIn('term_subjects.class_id', $classIds)
            ->when(Schema::hasTable('student_subject_exclusions'), function ($query) use ($schoolId, $session, $student) {
                $query->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $session, $student) {
                    $join->on('student_subject_exclusions.class_id', '=', 'term_subjects.class_id')
                        ->on('student_subject_exclusions.subject_id', '=', 'term_subjects.subject_id')
                        ->where('student_subject_exclusions.school_id', '=', $schoolId)
                        ->where('student_subject_exclusions.academic_session_id', '=', (int) $session->id)
                        ->where('student_subject_exclusions.student_id', '=', (int) $student->id);
                })
                ->whereNull('student_subject_exclusions.id');
            })
            ->get(['term_subjects.id', 'term_subjects.subject_id']);

        $allowedTermSubjectIds = $allowedTermSubjects
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $allowedSubjectIds = $allowedTermSubjects
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $termSubjectToSubjectId = $allowedTermSubjects
            ->mapWithKeys(fn ($row) => [(int) $row->id => (int) $row->subject_id])
            ->all();

        if (empty($allowedTermSubjectIds)) return response()->json(['data' => []]);

        if ($filterTermSubjectId && !in_array((int)$filterTermSubjectId, array_map('intval', $allowedTermSubjectIds), true)) {
            return response()->json(['data' => []]); // or 403
        }

        $query = ELibraryBook::query()
            ->where('school_id', $schoolId);

        if (Schema::hasColumn('e_library_books', 'term_subject_id')) {
            $query->whereIn('term_subject_id', $allowedTermSubjectIds);

            if ($filterTermSubjectId) {
                $query->where('term_subject_id', (int)$filterTermSubjectId);
            }
        } elseif (Schema::hasColumn('e_library_books', 'subject_id')) {
            if (empty($allowedSubjectIds)) {
                return response()->json(['data' => []]);
            }

            $query->whereIn('subject_id', $allowedSubjectIds);

            if ($filterTermSubjectId) {
                $mappedSubjectId = $termSubjectToSubjectId[(int) $filterTermSubjectId] ?? null;
                if (!$mappedSubjectId) {
                    return response()->json(['data' => []]);
                }
                $query->where('subject_id', (int) $mappedSubjectId);
            }
        } else {
            return response()->json(['data' => []]);
        }

        $items = $query->orderByDesc('id')->get()->map(function ($b) {
            $path = trim((string) ($b->file_path ?? ''));
            $b->file_url = $path !== '' ? Storage::disk('public')->url($path) : null;
            return $b;
        });

        return response()->json(['data' => $items]);
    }
}
