<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Student\ResultsController as StudentResultsController;
use App\Models\AcademicSession;
use App\Models\Term;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ReportsController extends Controller
{
    // GET /api/school-admin/reports/student-result/options
    public function studentResultOptions(Request $request)
    {
        if (! $this->resultsPublished($request)) {
            return response()->json([
                'message' => 'Results are not yet published for your school.',
            ], 403);
        }

        return app(TranscriptController::class)->options($request);
    }

    // GET /api/school-admin/reports/student-result?email=...&academic_session_id=...&term_id=...
    public function studentResult(Request $request)
    {
        if (! $this->resultsPublished($request)) {
            return response()->json([
                'message' => 'Results are not yet published for your school.',
            ], 403);
        }

        $request->query->set('scope', 'single');
        $request->merge(['scope' => 'single']);

        return app(TranscriptController::class)->show($request);
    }

    // GET /api/school-admin/reports/student-result/download?email=...&academic_session_id=...&term_id=...
    public function studentResultDownload(Request $request)
    {
        if (! $this->resultsPublished($request)) {
            return response()->json([
                'message' => 'Results are not yet published for your school.',
            ], 403);
        }

        return app(StudentResultsController::class)->downloadForSchoolAdmin($request);
    }

    // GET /api/school-admin/reports/broadsheet/options
    public function broadsheetOptions(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $requestedSessionId = (int) $request->query('academic_session_id', 0);
        $sessions = AcademicSession::where('school_id', $schoolId)
            ->orderByDesc('id')
            ->get(['id', 'session_name', 'academic_year', 'status']);

        $selectedSession = $requestedSessionId > 0
            ? $sessions->firstWhere('id', $requestedSessionId)
            : null;
        if (!$selectedSession) {
            $selectedSession = $sessions->firstWhere('status', 'current') ?: $sessions->first();
        }

        if (!$selectedSession) {
            return response()->json([
                'data' => [
                    'sessions' => [],
                    'selected_session_id' => null,
                    'levels' => [],
                    'selected_level' => null,
                ],
            ]);
        }

        $levels = $this->sessionLevelOptions($schoolId, (int) $selectedSession->id);
        $selectedLevel = $levels[0] ?? null;

        return response()->json([
            'data' => [
                'sessions' => $sessions->map(function (AcademicSession $session) {
                    return [
                        'id' => (int) $session->id,
                        'session_name' => $session->session_name,
                        'academic_year' => $session->academic_year,
                        'status' => $session->status,
                        'is_current' => $session->status === 'current',
                    ];
                })->values()->all(),
                'selected_session_id' => (int) $selectedSession->id,
                'levels' => $levels,
                'selected_level' => $selectedLevel,
            ],
        ]);
    }

    // GET /api/school-admin/reports/broadsheet?academic_session_id=1&level=secondary
    public function broadsheet(Request $request)
    {
        $payload = $request->validate([
            'academic_session_id' => 'nullable|integer',
            'level' => 'nullable|in:nursery,primary,secondary',
        ]);

        $schoolId = (int) $request->user()->school_id;
        $session = $this->resolveBroadsheetSession($schoolId, (int) ($payload['academic_session_id'] ?? 0));
        if (!$session) {
            return response()->json([
                'message' => 'No academic session configured for this school.',
            ], 422);
        }

        $levels = $this->sessionLevelOptions($schoolId, (int) $session->id);
        if (empty($levels)) {
            return response()->json([
                'message' => 'No class level found for the selected academic session.',
            ], 422);
        }

        $level = strtolower((string) ($payload['level'] ?? $levels[0]));
        if (!in_array($level, $levels, true)) {
            $level = $levels[0];
        }

        $broadsheet = $this->buildBroadsheetData($schoolId, (int) $session->id, $level);

        return response()->json([
            'data' => $broadsheet,
            'context' => [
                'session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                    'status' => $session->status,
                ],
                'level' => $level,
                'levels' => $levels,
            ],
        ]);
    }

    // GET /api/school-admin/reports/broadsheet/download?academic_session_id=1&level=secondary
    public function broadsheetDownload(Request $request)
    {
        $payload = $request->validate([
            'academic_session_id' => 'nullable|integer',
            'level' => 'nullable|in:nursery,primary,secondary',
        ]);

        $schoolId = (int) $request->user()->school_id;
        $session = $this->resolveBroadsheetSession($schoolId, (int) ($payload['academic_session_id'] ?? 0));
        if (!$session) {
            return response()->json([
                'message' => 'No academic session configured for this school.',
            ], 422);
        }

        $levels = $this->sessionLevelOptions($schoolId, (int) $session->id);
        if (empty($levels)) {
            return response()->json([
                'message' => 'No class level found for the selected academic session.',
            ], 422);
        }

        $level = strtolower((string) ($payload['level'] ?? $levels[0]));
        if (!in_array($level, $levels, true)) {
            $level = $levels[0];
        }

        $broadsheet = $this->buildBroadsheetData($schoolId, (int) $session->id, $level);
        if (empty($broadsheet['subjects']) || empty($broadsheet['rows'])) {
            return response()->json([
                'message' => 'No broadsheet result data found for the selected filters.',
            ], 404);
        }

        try {
            $schoolName = $request->user()?->school?->name ?? 'School';
            $html = view('pdf.school_admin_broadsheet', [
                'schoolName' => $schoolName,
                'session' => $session,
                'level' => $level,
                'subjects' => $broadsheet['subjects'],
                'rows' => $broadsheet['rows'],
            ])->render();

            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));
            $filename = sprintf('broadsheet_%s_%s.pdf', Str::slug($level), $safeSession);

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Throwable $e) {
            Log::error('Broadsheet PDF generation failed', [
                'school_id' => $schoolId,
                'session_id' => $session->id,
                'level' => $level,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to generate broadsheet PDF right now.',
            ], 500);
        }
    }

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

        $rows = $this->teacherRows($schoolId, (int) $term->id);

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

        $rows = $this->studentRows($schoolId, (int) $term->id);

        return response()->json([
            'data' => $rows,
            'context' => $this->contextPayload($session, $term, $terms),
        ]);
    }

    // GET /api/school-admin/reports/teacher/download?term_id=1
    public function teacherDownload(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term, $terms] = $this->resolveCurrentSessionAndSelectedTerm($request, $schoolId);

        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $rows = $this->teacherRows($schoolId, (int) $term->id);
        $context = $this->contextPayload($session, $term, $terms);
        $schoolName = $request->user()?->school?->name ?? 'School';

        return $this->pdfResponse(
            'Teacher Report',
            $schoolName,
            $context,
            $rows,
            'teacher_report'
        );
    }

    // GET /api/school-admin/reports/student/download?term_id=1
    public function studentDownload(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term, $terms] = $this->resolveCurrentSessionAndSelectedTerm($request, $schoolId);

        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $rows = $this->studentRows($schoolId, (int) $term->id);
        $context = $this->contextPayload($session, $term, $terms);
        $schoolName = $request->user()?->school?->name ?? 'School';

        return $this->pdfResponse(
            'Student Report',
            $schoolName,
            $context,
            $rows,
            'student_report'
        );
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

    private function teacherRows(int $schoolId, int $termId)
    {
        return DB::table('results')
            ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
            ->join('users as teachers', 'teachers.id', '=', 'term_subjects.teacher_user_id')
            ->where('results.school_id', $schoolId)
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.term_id', $termId)
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
    }

    private function studentRows(int $schoolId, int $termId)
    {
        return DB::table('results')
            ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
            ->join('students', 'students.id', '=', 'results.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('results.school_id', $schoolId)
            ->where('term_subjects.school_id', $schoolId)
            ->where('students.school_id', $schoolId)
            ->where('term_subjects.term_id', $termId)
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
    }

    private function resolveBroadsheetSession(int $schoolId, int $requestedSessionId): ?AcademicSession
    {
        if ($requestedSessionId > 0) {
            $selected = AcademicSession::where('school_id', $schoolId)
                ->where('id', $requestedSessionId)
                ->first(['id', 'session_name', 'academic_year', 'status']);
            if ($selected) {
                return $selected;
            }
        }

        return AcademicSession::where('school_id', $schoolId)
            ->orderByRaw("CASE WHEN status = 'current' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first(['id', 'session_name', 'academic_year', 'status']);
    }

    private function sessionLevelOptions(int $schoolId, int $sessionId): array
    {
        $levels = DB::table('classes')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->select('level')
            ->distinct()
            ->pluck('level')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        $allowed = ['nursery', 'primary', 'secondary'];
        $levels = array_values(array_filter($levels, fn ($level) => in_array($level, $allowed, true)));

        usort($levels, function (string $a, string $b) use ($allowed) {
            return array_search($a, $allowed, true) <=> array_search($b, $allowed, true);
        });

        return $levels;
    }

    private function buildBroadsheetData(int $schoolId, int $sessionId, string $level): array
    {
        $classes = DB::table('classes')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('level', $level)
            ->get(['id', 'name', 'level']);

        if ($classes->isEmpty()) {
            return [
                'classes' => [],
                'subjects' => [],
                'rows' => [],
            ];
        }

        $classList = $classes
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'level' => strtolower((string) $row->level),
                ];
            })
            ->sortBy(fn ($item) => $this->classOrderIndex((string) $item['name']))
            ->values();

        $classIds = $classList->pluck('id')->all();
        $classNameById = $classList->pluck('name', 'id')->all();
        $classOrderById = [];
        foreach ($classList as $index => $item) {
            $classOrderById[(int) $item['id']] = $index;
        }

        $termIds = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($termIds)) {
            return [
                'classes' => $classList->all(),
                'subjects' => [],
                'rows' => [],
            ];
        }

        $termSubjects = DB::table('term_subjects')
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->where('term_subjects.school_id', $schoolId)
            ->whereIn('term_subjects.class_id', $classIds)
            ->whereIn('term_subjects.term_id', $termIds)
            ->select([
                'term_subjects.id as term_subject_id',
                'term_subjects.class_id',
                'term_subjects.term_id',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
            ])
            ->get();

        if ($termSubjects->isEmpty()) {
            return [
                'classes' => $classList->all(),
                'subjects' => [],
                'rows' => [],
            ];
        }

        $termSubjectMetaById = [];
        $classSubjectSet = [];
        foreach ($termSubjects as $item) {
            $termSubjectId = (int) $item->term_subject_id;
            $classId = (int) $item->class_id;
            $subjectId = (int) $item->subject_id;

            $termSubjectMetaById[$termSubjectId] = [
                'class_id' => $classId,
                'subject_id' => $subjectId,
            ];

            if (!isset($classSubjectSet[$classId])) {
                $classSubjectSet[$classId] = [];
            }
            $classSubjectSet[$classId][$subjectId] = true;
        }

        $subjects = $termSubjects
            ->groupBy(fn ($item) => (int) $item->subject_id)
            ->map(function ($rows, $subjectId) {
                $sample = $rows->first();
                $code = (string) ($sample->subject_code ?? '');
                return [
                    'id' => (int) $subjectId,
                    'name' => (string) $sample->subject_name,
                    'code' => $code,
                    'short_code' => $this->subjectShortCode((string) $sample->subject_name, $code),
                ];
            })
            ->values()
            ->sortBy(fn ($item) => strtoupper((string) ($item['short_code'] ?: $item['name'])))
            ->values();

        $subjectIds = $subjects->pluck('id')->all();
        $subjectKeyById = [];
        foreach ($subjects as $subject) {
            $subjectKeyById[(int) $subject['id']] = (string) $subject['id'];
        }

        $resultRows = DB::table('results')
            ->where('results.school_id', $schoolId)
            ->whereIn('results.term_subject_id', array_keys($termSubjectMetaById))
            ->select([
                'results.student_id',
                'results.term_subject_id',
                'results.ca',
                'results.exam',
            ])
            ->get();

        $studentsFromClassStudents = DB::table('class_students as cs')
            ->join('students as s', 's.id', '=', 'cs.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('cs.school_id', $schoolId)
            ->where('cs.academic_session_id', $sessionId)
            ->whereIn('cs.class_id', $classIds)
            ->select([
                'cs.id as class_student_id',
                'cs.class_id',
                's.id as student_id',
                'u.name as student_name',
                'u.email as student_email',
            ])
            ->orderBy('u.name')
            ->get();

        $studentMeta = [];
        foreach ($studentsFromClassStudents as $row) {
            $studentId = (int) $row->student_id;
            $classStudentId = (int) $row->class_student_id;
            if (!isset($studentMeta[$studentId]) || $classStudentId > $studentMeta[$studentId]['class_student_id']) {
                $studentMeta[$studentId] = [
                    'class_student_id' => $classStudentId,
                    'class_id' => (int) $row->class_id,
                    'name' => (string) $row->student_name,
                    'email' => (string) $row->student_email,
                ];
            }
        }

        if (empty($studentMeta)) {
            $studentsFromResults = DB::table('results')
                ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
                ->join('students as s', 's.id', '=', 'results.student_id')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->where('results.school_id', $schoolId)
                ->whereIn('term_subjects.class_id', $classIds)
                ->whereIn('term_subjects.term_id', $termIds)
                ->select([
                    's.id as student_id',
                    'u.name as student_name',
                    'u.email as student_email',
                    DB::raw('MAX(term_subjects.class_id) as class_id'),
                ])
                ->groupBy('s.id', 'u.name', 'u.email')
                ->get();

            foreach ($studentsFromResults as $row) {
                $studentId = (int) $row->student_id;
                $studentMeta[$studentId] = [
                    'class_student_id' => 0,
                    'class_id' => (int) $row->class_id,
                    'name' => (string) $row->student_name,
                    'email' => (string) $row->student_email,
                ];
            }
        }

        $scoreBuckets = [];
        foreach ($resultRows as $resultRow) {
            $termSubjectId = (int) $resultRow->term_subject_id;
            $studentId = (int) $resultRow->student_id;
            $meta = $termSubjectMetaById[$termSubjectId] ?? null;
            if (!$meta) {
                continue;
            }

            $subjectId = (int) $meta['subject_id'];
            $score = (int) $resultRow->ca + (int) $resultRow->exam;

            if (!isset($scoreBuckets[$studentId])) {
                $scoreBuckets[$studentId] = [];
            }
            if (!isset($scoreBuckets[$studentId][$subjectId])) {
                $scoreBuckets[$studentId][$subjectId] = ['sum' => 0, 'count' => 0];
            }
            $scoreBuckets[$studentId][$subjectId]['sum'] += $score;
            $scoreBuckets[$studentId][$subjectId]['count'] += 1;
        }

        $rows = [];
        foreach ($studentMeta as $studentId => $meta) {
            $classId = (int) $meta['class_id'];
            $offeredSubjects = $classSubjectSet[$classId] ?? [];
            $subjectScores = [];
            $total = 0.0;
            $scoredCount = 0;

            foreach ($subjectIds as $subjectId) {
                $key = $subjectKeyById[$subjectId];
                if (!isset($offeredSubjects[$subjectId])) {
                    $subjectScores[$key] = null;
                    continue;
                }

                $bucket = $scoreBuckets[$studentId][$subjectId] ?? null;
                if (!$bucket || (int) $bucket['count'] <= 0) {
                    $subjectScores[$key] = null;
                    continue;
                }

                $value = round(((float) $bucket['sum']) / max(1, (int) $bucket['count']), 2);
                $subjectScores[$key] = $value;
                $total += $value;
                $scoredCount++;
            }

            $average = $scoredCount > 0 ? round($total / $scoredCount, 2) : 0.0;
            $rows[] = [
                'student_id' => (int) $studentId,
                'name' => (string) $meta['name'],
                'email' => (string) $meta['email'],
                'class_id' => $classId,
                'class_name' => (string) ($classNameById[$classId] ?? '-'),
                'scores' => $subjectScores,
                'total' => round($total, 2),
                'average' => $average,
                'position' => null,
                'position_label' => '-',
            ];
        }

        usort($rows, function (array $a, array $b) use ($classOrderById) {
            $classCmp = ($classOrderById[$a['class_id']] ?? 999) <=> ($classOrderById[$b['class_id']] ?? 999);
            if ($classCmp !== 0) {
                return $classCmp;
            }
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        $ranking = $rows;
        usort($ranking, function (array $a, array $b) {
            if ((float) $a['average'] === (float) $b['average']) {
                return strcasecmp((string) $a['name'], (string) $b['name']);
            }
            return ((float) $b['average']) <=> ((float) $a['average']);
        });

        $positionByStudent = [];
        $previousAverage = null;
        $previousRank = 0;
        foreach ($ranking as $index => $item) {
            $average = (float) $item['average'];
            $rank = ($previousAverage !== null && abs($average - $previousAverage) < 0.00001)
                ? $previousRank
                : ($index + 1);

            $positionByStudent[(int) $item['student_id']] = $rank;
            $previousAverage = $average;
            $previousRank = $rank;
        }

        foreach ($rows as &$row) {
            $position = (int) ($positionByStudent[(int) $row['student_id']] ?? 0);
            $row['position'] = $position;
            $row['position_label'] = $position > 0 ? $this->ordinalPosition($position) : '-';
        }
        unset($row);

        return [
            'classes' => $classList->all(),
            'subjects' => $subjects->values()->all(),
            'rows' => $rows,
        ];
    }

    private function classOrderIndex(string $className): int
    {
        $name = strtoupper(trim($className));
        if ($name === '') {
            return 1000;
        }

        if (preg_match('/NURSERY\s*(\d+)/i', $name, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/PRIMARY\s*(\d+)/i', $name, $m)) {
            return 100 + (int) $m[1];
        }
        if (preg_match('/JS\s*(\d+)/i', $name, $m)) {
            return 200 + (int) $m[1];
        }
        if (preg_match('/SS\s*(\d+)/i', $name, $m)) {
            return 300 + (int) $m[1];
        }
        if (preg_match('/GRADE\s*(\d+)/i', $name, $m)) {
            return 100 + (int) $m[1];
        }

        return 1000;
    }

    private function subjectShortCode(string $subjectName, string $subjectCode = ''): string
    {
        $baseCode = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($subjectCode)));
        $nameLetters = preg_replace('/[^A-Z]/', '', strtoupper(trim($subjectName)));
        $combined = (string) $baseCode;

        if (strlen($combined) < 3) {
            $combined .= (string) $nameLetters;
        }

        if ($combined === '') {
            $combined = 'SUB';
        }

        return substr(str_pad($combined, 3, 'X'), 0, 3);
    }

    private function ordinalPosition(int $position): string
    {
        if ($position <= 0) {
            return '-';
        }

        $mod100 = $position % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return $position . 'th';
        }

        return match ($position % 10) {
            1 => $position . 'st',
            2 => $position . 'nd',
            3 => $position . 'rd',
            default => $position . 'th',
        };
    }

    private function pdfResponse(string $title, string $schoolName, array $context, $rows, string $prefix)
    {
        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.school_admin_report', [
                'title' => $title,
                'schoolName' => $schoolName,
                'context' => $context,
                'rows' => $rows,
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $sessionName = $context['current_session']['session_name'] ?? $context['current_session']['academic_year'] ?? 'session';
            $termName = $context['selected_term']['name'] ?? 'term';
            $filename = sprintf(
                '%s_%s_%s.pdf',
                Str::slug($prefix),
                Str::slug((string) $sessionName),
                Str::slug((string) $termName)
            );

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Throwable $e) {
            Log::error('School admin report PDF generation failed', [
                'title' => $title,
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to generate report PDF right now.',
            ], 500);
        }
    }

    private function resultsPublished(Request $request): bool
    {
        $school = $request->user()?->school;
        return (bool) ($school?->results_published);
    }
}
