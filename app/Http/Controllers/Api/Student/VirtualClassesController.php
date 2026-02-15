<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermSubject;
use App\Models\VirtualClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VirtualClassesController extends Controller
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

    private function allowedTermSubjectIds(Request $request): array
    {
        $subjectsRes = $this->mySubjects($request)->getData(true);
        return collect($subjectsRes['data'] ?? [])
            ->pluck('term_subject_id')
            ->map(fn($v) => (int) $v)
            ->toArray();
    }

    // GET /api/student/virtual-classes/subjects
    public function mySubjects(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        $schoolId = $user->school_id;
        $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
        if (!$session) return response()->json(['data' => []]);

        $currentTermId = $this->resolveCurrentTermId((int)$schoolId, (int)$session->id);
        if (!$currentTermId) return response()->json(['data' => []]);

        $student = Student::where('user_id', $user->id)->where('school_id', $schoolId)->first();
        if (!$student) return response()->json(['data' => []]);

        $enrollQuery = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('term_id', $currentTermId);
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollQuery->where('school_id', $schoolId);
        }
        $enrollments = $enrollQuery->get(['class_id', 'term_id']);
        if ($enrollments->isEmpty()) return response()->json(['data' => []]);

        $rows = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.term_id', $currentTermId)
            ->where(function ($q) use ($enrollments) {
                foreach ($enrollments as $e) {
                    $q->orWhere(function ($qq) use ($e) {
                        $qq->where('term_subjects.class_id', $e->class_id)
                            ->where('term_subjects.term_id', $e->term_id);
                    });
                }
            })
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
            ->orderBy('subjects.name')
            ->get([
                'term_subjects.id as term_subject_id',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
                'classes.name as class_name',
                'classes.level as class_level',
                'terms.name as term_name',
            ]);

        return response()->json(['data' => $rows]);
    }

    // GET /api/student/virtual-classes?term_subject_id=&subject_id=
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        $schoolId = $user->school_id;
        $data = $request->validate([
            'term_subject_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $allowed = $this->allowedTermSubjectIds($request);
        if (empty($allowed)) return response()->json(['data' => []]);

        if (!empty($data['term_subject_id']) && !in_array((int) $data['term_subject_id'], $allowed, true)) {
            return response()->json(['data' => []]);
        }

        $items = VirtualClass::query()
            ->join('term_subjects', 'term_subjects.id', '=', 'virtual_classes.term_subject_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
            ->where('virtual_classes.school_id', $schoolId)
            ->whereIn('virtual_classes.term_subject_id', $allowed)
            ->when(!empty($data['term_subject_id']), function ($q) use ($data) {
                $q->where('virtual_classes.term_subject_id', (int) $data['term_subject_id']);
            })
            ->when(!empty($data['subject_id']), function ($q) use ($data) {
                $q->where('term_subjects.subject_id', (int) $data['subject_id']);
            })
            ->orderByDesc('virtual_classes.id')
            ->get([
                'virtual_classes.*',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'classes.name as class_name',
                'classes.level as class_level',
                'terms.name as term_name',
            ]);

        return response()->json(['data' => $items]);
    }
}
