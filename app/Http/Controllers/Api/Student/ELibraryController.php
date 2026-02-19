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
        $classIds = DB::table('class_students')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('student_id', $studentId)
            ->pluck('class_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (!empty($classIds)) {
            return $classIds;
        }

        $enrollQuery = Enrollment::query()->where('student_id', $studentId);
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollQuery->where('school_id', $schoolId);
        }

        return $enrollQuery
            ->where('term_id', $currentTermId)
            ->pluck('class_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
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

        $allowedTermSubjectIds = TermSubject::query()
            ->where('school_id', $schoolId)
            ->where('term_id', $currentTermId)
            ->whereIn('class_id', $classIds)
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
