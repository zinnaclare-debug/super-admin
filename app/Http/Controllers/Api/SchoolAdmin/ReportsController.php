<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    // GET /api/school-admin/reports/teacher?term_id=1
    public function teacher(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term, $terms] = $this->resolveCurrentSessionAndSelectedTerm($request, $schoolId);

        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $rows = DB::table('results')
            ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
            ->join('users as teachers', 'teachers.id', '=', 'term_subjects.teacher_user_id')
            ->where('results.school_id', $schoolId)
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.term_id', $term->id)
            ->select([
                'teachers.id as teacher_user_id',
                'teachers.name as teacher_name',
                'teachers.email as teacher_email',
                DB::raw('COUNT(*) as total_graded'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) >= 70 THEN 1 ELSE 0 END) as grade_a'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 60 AND 69 THEN 1 ELSE 0 END) as grade_b'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 50 AND 59 THEN 1 ELSE 0 END) as grade_c'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 40 AND 49 THEN 1 ELSE 0 END) as grade_d'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 30 AND 39 THEN 1 ELSE 0 END) as grade_e'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) < 30 THEN 1 ELSE 0 END) as grade_f'),
            ])
            ->groupBy('teachers.id', 'teachers.name', 'teachers.email')
            ->orderBy('teachers.name')
            ->get()
            ->map(function ($row, $index) {
                return [
                    'sn' => $index + 1,
                    'teacher_user_id' => (int) $row->teacher_user_id,
                    'name' => $row->teacher_name,
                    'email' => $row->teacher_email,
                    'total_graded' => (int) $row->total_graded,
                    'grades' => [
                        'A' => (int) $row->grade_a,
                        'B' => (int) $row->grade_b,
                        'C' => (int) $row->grade_c,
                        'D' => (int) $row->grade_d,
                        'E' => (int) $row->grade_e,
                        'F' => (int) $row->grade_f,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'context' => $this->contextPayload($session, $term, $terms),
        ]);
    }

    // GET /api/school-admin/reports/student?term_id=1
    public function student(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term, $terms] = $this->resolveCurrentSessionAndSelectedTerm($request, $schoolId);

        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $rows = DB::table('results')
            ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
            ->join('students', 'students.id', '=', 'results.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('results.school_id', $schoolId)
            ->where('term_subjects.school_id', $schoolId)
            ->where('students.school_id', $schoolId)
            ->where('term_subjects.term_id', $term->id)
            ->select([
                'students.id as student_id',
                'users.name as student_name',
                'users.email as student_email',
                DB::raw('COUNT(*) as total_graded'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) >= 70 THEN 1 ELSE 0 END) as grade_a'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 60 AND 69 THEN 1 ELSE 0 END) as grade_b'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 50 AND 59 THEN 1 ELSE 0 END) as grade_c'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 40 AND 49 THEN 1 ELSE 0 END) as grade_d'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) BETWEEN 30 AND 39 THEN 1 ELSE 0 END) as grade_e'),
                DB::raw('SUM(CASE WHEN (results.ca + results.exam) < 30 THEN 1 ELSE 0 END) as grade_f'),
            ])
            ->groupBy('students.id', 'users.name', 'users.email')
            ->orderByDesc('total_graded')
            ->orderBy('users.name')
            ->get()
            ->map(function ($row, $index) {
                return [
                    'sn' => $index + 1,
                    'student_id' => (int) $row->student_id,
                    'name' => $row->student_name,
                    'email' => $row->student_email,
                    'total_graded' => (int) $row->total_graded,
                    'grades' => [
                        'A' => (int) $row->grade_a,
                        'B' => (int) $row->grade_b,
                        'C' => (int) $row->grade_c,
                        'D' => (int) $row->grade_d,
                        'E' => (int) $row->grade_e,
                        'F' => (int) $row->grade_f,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'context' => $this->contextPayload($session, $term, $terms),
        ]);
    }

    private function resolveCurrentSessionAndSelectedTerm(Request $request, int $schoolId): array
    {
        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();
        if (!$session) {
            return [null, null, collect()];
        }

        $terms = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'name', 'is_current']);

        $selectedTermId = (int) $request->query('term_id', 0);
        $term = null;

        if ($selectedTermId > 0) {
            $term = $terms->firstWhere('id', $selectedTermId);
        }

        if (!$term) {
            $term = $terms->firstWhere('is_current', true) ?: $terms->first();
        }

        return [$session, $term, $terms];
    }

    private function contextPayload(AcademicSession $session, Term $term, $terms): array
    {
        return [
            'current_session' => [
                'id' => (int) $session->id,
                'session_name' => $session->session_name,
                'academic_year' => $session->academic_year,
            ],
            'selected_term' => [
                'id' => (int) $term->id,
                'name' => $term->name,
            ],
            'terms' => $terms->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'is_current' => (bool) $item->is_current,
                ];
            })->values(),
        ];
    }
}

