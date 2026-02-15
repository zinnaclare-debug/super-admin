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

        $enrollQuery = Enrollment::query()
            ->where('student_id', $student->id);

        // If enrollments has school_id column in your DB, enforce it
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollQuery->where('school_id', $schoolId);
        }

        // ✅ only terms that belong to current academic session
        $enrollments = $enrollQuery
            ->join('terms', 'terms.id', '=', 'enrollments.term_id')
            ->where('terms.academic_session_id', $session->id)
            ->where('enrollments.term_id', $currentTermId)
            ->get(['enrollments.class_id', 'enrollments.term_id']);

        if ($enrollments->isEmpty()) return response()->json(['data' => []]);

        $termSubjects = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where(function ($q) use ($enrollments) {
                foreach ($enrollments as $e) {
                    $q->orWhere(function ($qq) use ($e) {
                        $qq->where('term_subjects.class_id', $e->class_id)
                           ->where('term_subjects.term_id', $e->term_id);
                    });
                }
            })
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
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

        $enrollQuery = Enrollment::query()
            ->where('student_id', $student->id);

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollQuery->where('school_id', $schoolId);
        }

        // Only current session terms
        $enrollments = $enrollQuery
            ->join('terms', 'terms.id', '=', 'enrollments.term_id')
            ->where('terms.academic_session_id', $session->id)
            ->where('enrollments.term_id', $currentTermId)
            ->get(['enrollments.class_id', 'enrollments.term_id']);

        if ($enrollments->isEmpty()) return response()->json(['data' => []]);

        $allowedTermSubjectIds = TermSubject::query()
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($enrollments) {
                foreach ($enrollments as $e) {
                    $q->orWhere(function ($qq) use ($e) {
                        $qq->where('class_id', $e->class_id)->where('term_id', $e->term_id);
                    });
                }
            })
            ->pluck('id')
            ->toArray();

        if (empty($allowedTermSubjectIds)) return response()->json(['data' => []]);

        if ($filterTermSubjectId && !in_array((int)$filterTermSubjectId, array_map('intval', $allowedTermSubjectIds), true)) {
            return response()->json(['data' => []]); // or 403
        }

        $query = ELibraryBook::query()
            ->where('school_id', $schoolId)
            ->whereIn('term_subject_id', $allowedTermSubjectIds);

        if ($filterTermSubjectId) {
            $query->where('term_subject_id', (int)$filterTermSubjectId);
        }

        $items = $query->orderByDesc('id')->get()->map(function ($b) {
            $b->file_url = Storage::disk('public')->url($b->file_path);
            return $b;
        });

        return response()->json(['data' => $items]);
    }
}
