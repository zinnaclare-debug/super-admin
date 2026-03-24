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
use Illuminate\Support\Facades\Schema;
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

        return app(StudentResultsController::class)->showForSchoolAdmin($request);
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

    // GET /api/school-admin/reports/broadsheet/options?academic_session_id=1&level=secondary&department=Science&class_id=10
    public function broadsheetOptions(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $requestedSessionId = (int) $request->query('academic_session_id', 0);
        $requestedLevel = strtolower(trim((string) $request->query('level', '')));
        $requestedDepartment = trim((string) $request->query('department', ''));
        $requestedClassId = (int) $request->query('class_id', 0);
        $reportScope = $this->normalizeBroadsheetReportScope((string) $request->query('report_scope', 'annual'));
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
                    'departments' => [],
                    'selected_department' => null,
                    'classes' => [],
                    'selected_class_id' => null,
                    'report_scopes' => $this->broadsheetReportScopeOptions(),
                    'selected_report_scope' => $reportScope,
                ],
            ]);
        }

        $levels = $this->sessionLevelOptions($schoolId, (int) $selectedSession->id);
        $selectedLevel = in_array($requestedLevel, $levels, true)
            ? $requestedLevel
            : ($levels[0] ?? null);
        $departments = $selectedLevel
            ? $this->sessionDepartmentOptions($schoolId, (int) $selectedSession->id, $selectedLevel)
            : [];
        $selectedDepartment = $this->resolveDepartmentFilter($requestedDepartment, $departments);
        $classes = $selectedLevel
            ? $this->sessionClassOptions(
                $schoolId,
                (int) $selectedSession->id,
                $selectedLevel,
                $selectedDepartment
            )
            : [];
        $selectedClassId = $this->resolveClassFilter($requestedClassId, $classes);

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
                'departments' => $departments,
                'selected_department' => $selectedDepartment,
                'classes' => $classes,
                'selected_class_id' => $selectedClassId,
                'report_scopes' => $this->broadsheetReportScopeOptions(),
                'selected_report_scope' => $reportScope,
            ],
        ]);
    }

    // GET /api/school-admin/reports/broadsheet?academic_session_id=1&level=secondary&department=Science&class_id=10
    public function broadsheet(Request $request)
    {
        $payload = $request->validate([
            'academic_session_id' => 'nullable|integer',
            'level' => 'nullable|string|max:60',
            'department' => 'nullable|string|max:100',
            'class_id' => 'nullable|integer',
            'report_scope' => 'nullable|string|in:annual,first_term,second_term,third_term',
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

        $departments = $this->sessionDepartmentOptions($schoolId, (int) $session->id, $level);
        $selectedDepartment = $this->resolveDepartmentFilter((string) ($payload['department'] ?? ''), $departments);
        if (($payload['department'] ?? null) && $selectedDepartment === null) {
            return response()->json([
                'message' => 'Invalid department selected for the chosen level.',
            ], 422);
        }

        $classes = $this->sessionClassOptions($schoolId, (int) $session->id, $level, $selectedDepartment);
        $selectedClassId = $this->resolveClassFilter((int) ($payload['class_id'] ?? 0), $classes);
        if (($payload['class_id'] ?? null) && $selectedClassId === null) {
            return response()->json([
                'message' => 'Invalid class selected for the chosen filters.',
            ], 422);
        }
        $reportScope = $this->normalizeBroadsheetReportScope((string) ($payload['report_scope'] ?? 'annual'));

        $broadsheet = $this->buildBroadsheetData(
            $schoolId,
            (int) $session->id,
            $level,
            $selectedDepartment,
            $selectedClassId,
            $reportScope
        );
        $selectedClassName = collect($classes)
            ->firstWhere('id', $selectedClassId)['name'] ?? null;

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
                'departments' => $departments,
                'selected_department' => $selectedDepartment,
                'classes' => $classes,
                'selected_class_id' => $selectedClassId,
                'selected_class_name' => $selectedClassName,
                'report_scopes' => $this->broadsheetReportScopeOptions(),
                'selected_report_scope' => $reportScope,
                'selected_report_scope_label' => $this->broadsheetReportScopeLabel($reportScope),
                'selected_term_name' => $broadsheet['selected_term_name'] ?? null,
            ],
        ]);
    }

    // GET /api/school-admin/reports/broadsheet/download?academic_session_id=1&level=secondary&department=Science&class_id=10
    public function broadsheetDownload(Request $request)
    {
        $payload = $request->validate([
            'academic_session_id' => 'nullable|integer',
            'level' => 'nullable|string|max:60',
            'department' => 'nullable|string|max:100',
            'class_id' => 'nullable|integer',
            'report_scope' => 'nullable|string|in:annual,first_term,second_term,third_term',
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

        $departments = $this->sessionDepartmentOptions($schoolId, (int) $session->id, $level);
        $selectedDepartment = $this->resolveDepartmentFilter((string) ($payload['department'] ?? ''), $departments);
        if (($payload['department'] ?? null) && $selectedDepartment === null) {
            return response()->json([
                'message' => 'Invalid department selected for the chosen level.',
            ], 422);
        }

        $classes = $this->sessionClassOptions($schoolId, (int) $session->id, $level, $selectedDepartment);
        $selectedClassId = $this->resolveClassFilter((int) ($payload['class_id'] ?? 0), $classes);
        if (($payload['class_id'] ?? null) && $selectedClassId === null) {
            return response()->json([
                'message' => 'Invalid class selected for the chosen filters.',
            ], 422);
        }
        $reportScope = $this->normalizeBroadsheetReportScope((string) ($payload['report_scope'] ?? 'annual'));

        $broadsheet = $this->buildBroadsheetData(
            $schoolId,
            (int) $session->id,
            $level,
            $selectedDepartment,
            $selectedClassId,
            $reportScope
        );
        if (empty($broadsheet['subjects']) || empty($broadsheet['rows'])) {
            return response()->json([
                'message' => 'No broadsheet result data found for the selected filters.',
            ], 404);
        }

        try {
            $schoolName = $request->user()?->school?->name ?? 'School';
            $selectedClassName = collect($classes)
                ->firstWhere('id', $selectedClassId)['name'] ?? null;
            $html = view('pdf.school_admin_broadsheet', [
                'schoolName' => $schoolName,
                'session' => $session,
                'level' => $level,
                'department' => $selectedDepartment,
                'className' => $selectedClassName,
                'reportScope' => $reportScope,
                'reportScopeLabel' => $this->broadsheetReportScopeLabel($reportScope),
                'selectedTermName' => $broadsheet['selected_term_name'] ?? null,
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

            $subjectCount = count($broadsheet['subjects'] ?? []);
            $paper = 'A4';
            if ($subjectCount >= 16) {
                $paper = 'A3';
            }
            if ($subjectCount >= 28) {
                $paper = 'A2';
            }

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper($paper, 'landscape');
            $dompdf->render();

            $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));
            $filename = sprintf(
                'broadsheet_%s_%s%s%s_%s.pdf',
                Str::slug($reportScope),
                Str::slug($level),
                $selectedDepartment ? ('_' . Str::slug($selectedDepartment)) : '',
                $selectedClassName ? ('_' . Str::slug($selectedClassName)) : '',
                $safeSession
            );

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Throwable $e) {
            Log::error('Broadsheet PDF generation failed', [
                'school_id' => $schoolId,
                'session_id' => $session->id,
                'level' => $level,
                'department' => $selectedDepartment,
                'class_id' => $selectedClassId,
                'report_scope' => $reportScope,
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
        $term = Term::where('school_id', $schoolId)
            ->where('id', $termId)
            ->first(['id', 'academic_session_id']);

        if (!$term) {
            return collect();
        }

        $sessionId = (int) ($term->academic_session_id ?? 0);
        $resultSnapshot = $this->teacherResultSnapshot($schoolId, $sessionId, $termId);
        $classDutySnapshot = $this->teacherClassDutySnapshot($schoolId, $sessionId, $termId);

        $teacherIds = collect(array_merge(
            $resultSnapshot['teacher_ids'] ?? [],
            $classDutySnapshot['teacher_ids'] ?? []
        ))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($teacherIds->isEmpty()) {
            return collect();
        }

        $teachers = DB::table('users')
            ->where('school_id', $schoolId)
            ->whereIn('id', $teacherIds->all())
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->keyBy(fn ($row) => (int) $row->id);

        return $teacherIds
            ->map(function ($teacherId) use ($teachers, $resultSnapshot, $classDutySnapshot) {
                $teacher = $teachers->get((int) $teacherId);
                if (!$teacher) {
                    return null;
                }

                $gradeCounts = $resultSnapshot['grade_counts_by_teacher'][(int) $teacherId]
                    ?? ['A' => '-', 'B' => '-', 'C' => '-', 'D' => '-', 'E' => '-', 'F' => '-'];
                $gradedCount = $resultSnapshot['graded_count_by_teacher'][(int) $teacherId] ?? 0;

                return [
                    'teacher_user_id' => (int) $teacher->id,
                    'name' => (string) ($teacher->name ?? '-'),
                    'email' => (string) ($teacher->email ?? '-'),
                    'teacher_comment' => $classDutySnapshot['comment_preview_by_teacher'][(int) $teacherId] ?? '-',
                    'summary' => $this->formatTeacherOutstandingSummary([
                        'result' => $resultSnapshot['outstanding_result_classes'][(int) $teacherId] ?? [],
                        'attendance' => $classDutySnapshot['outstanding_attendance_classes'][(int) $teacherId] ?? [],
                        'comment' => $classDutySnapshot['outstanding_comment_classes'][(int) $teacherId] ?? [],
                        'behaviour' => $classDutySnapshot['outstanding_behaviour_classes'][(int) $teacherId] ?? [],
                    ]),
                    'total_graded' => $gradedCount > 0 ? $gradedCount : '-',
                    'grades' => $gradedCount > 0
                        ? $gradeCounts
                        : ['A' => '-', 'B' => '-', 'C' => '-', 'D' => '-', 'E' => '-', 'F' => '-'],
                ];
            })
            ->filter()
            ->values()
            ->sortBy(fn ($row) => strtolower((string) ($row['name'] ?? '')))
            ->values()
            ->map(function (array $row, int $index) {
                $row['sn'] = $index + 1;
                return $row;
            })
            ->values();
    }

    private function teacherResultSnapshot(int $schoolId, int $sessionId, int $termId): array
    {
        $canUseExclusions = Schema::hasTable('student_subject_exclusions')
            && Schema::hasColumn('student_subject_exclusions', 'student_id')
            && Schema::hasColumn('student_subject_exclusions', 'school_id')
            && Schema::hasColumn('student_subject_exclusions', 'academic_session_id')
            && Schema::hasColumn('student_subject_exclusions', 'class_id')
            && Schema::hasColumn('student_subject_exclusions', 'subject_id');

        $query = DB::table('term_subjects')
            ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->leftJoin('enrollments', function ($join) use ($schoolId, $termId) {
                $join->on('enrollments.class_id', '=', 'term_subjects.class_id')
                    ->where('enrollments.term_id', '=', $termId);

                if (Schema::hasColumn('enrollments', 'school_id')) {
                    $join->where('enrollments.school_id', '=', $schoolId);
                }
            })
            ->leftJoin('results', function ($join) use ($schoolId) {
                $join->on('results.term_subject_id', '=', 'term_subjects.id')
                    ->on('results.student_id', '=', 'enrollments.student_id')
                    ->where('results.school_id', '=', $schoolId);
            })
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.term_id', $termId)
            ->whereNotNull('term_subjects.teacher_user_id');

        if ($canUseExclusions) {
            $query->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $sessionId) {
                $join->on('student_subject_exclusions.student_id', '=', 'enrollments.student_id')
                    ->on('student_subject_exclusions.class_id', '=', 'term_subjects.class_id')
                    ->on('student_subject_exclusions.subject_id', '=', 'term_subjects.subject_id')
                    ->where('student_subject_exclusions.school_id', '=', $schoolId)
                    ->where('student_subject_exclusions.academic_session_id', '=', $sessionId);
            });
        }

        $rows = $query
            ->select([
                'term_subjects.id as term_subject_id',
                'term_subjects.teacher_user_id',
                'classes.name as class_name',
                'enrollments.student_id as enrolled_student_id',
                ...($canUseExclusions
                    ? ['student_subject_exclusions.student_id as excluded_student_id']
                    : [DB::raw('NULL as excluded_student_id')]),
                'results.id as result_id',
                'results.ca',
                'results.exam',
                'results.created_at as result_created_at',
                'results.updated_at as result_updated_at',
            ])
            ->orderBy('classes.name')
            ->get();

        $gradeCountsByTeacher = [];
        $gradedCountByTeacher = [];
        $teacherIds = [];
        $assignmentStats = [];

        foreach ($rows as $row) {
            $teacherId = (int) ($row->teacher_user_id ?? 0);
            if ($teacherId < 1) {
                continue;
            }

            $teacherIds[$teacherId] = true;
            $gradeCountsByTeacher[$teacherId] = $gradeCountsByTeacher[$teacherId]
                ?? ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];
            $gradedCountByTeacher[$teacherId] = (int) ($gradedCountByTeacher[$teacherId] ?? 0);

            $termSubjectId = (int) ($row->term_subject_id ?? 0);
            if ($termSubjectId < 1) {
                continue;
            }

            $assignmentStats[$termSubjectId] = $assignmentStats[$termSubjectId]
                ?? [
                    'teacher_id' => $teacherId,
                    'class_name' => (string) ($row->class_name ?? '-'),
                    'eligible' => 0,
                    'graded' => 0,
                ];

            $studentId = isset($row->enrolled_student_id) ? (int) $row->enrolled_student_id : 0;
            $excludedStudentId = isset($row->excluded_student_id) ? (int) $row->excluded_student_id : 0;
            if ($studentId < 1 || $excludedStudentId > 0) {
                continue;
            }

            $assignmentStats[$termSubjectId]['eligible']++;

            if (!$this->isResultRecordGraded(
                $row->result_id ?? null,
                $row->ca ?? null,
                $row->exam ?? null,
                $row->result_created_at ?? null,
                $row->result_updated_at ?? null
            )) {
                continue;
            }

            $assignmentStats[$termSubjectId]['graded']++;
            $gradedCountByTeacher[$teacherId]++;
            $band = $this->gradeBandFromScore((int) ($row->ca ?? 0) + (int) ($row->exam ?? 0));
            $gradeCountsByTeacher[$teacherId][$band] = (int) ($gradeCountsByTeacher[$teacherId][$band] ?? 0) + 1;
        }

        $outstandingResultClasses = [];
        foreach ($assignmentStats as $stats) {
            if ((int) ($stats['eligible'] ?? 0) < 1) {
                continue;
            }
            if ((int) ($stats['graded'] ?? 0) >= (int) ($stats['eligible'] ?? 0)) {
                continue;
            }

            $teacherId = (int) ($stats['teacher_id'] ?? 0);
            $className = (string) ($stats['class_name'] ?? '-');
            $outstandingResultClasses[$teacherId] = $outstandingResultClasses[$teacherId] ?? [];
            $outstandingResultClasses[$teacherId][$className] = $className;
        }

        return [
            'teacher_ids' => array_map('intval', array_keys($teacherIds)),
            'grade_counts_by_teacher' => $gradeCountsByTeacher,
            'graded_count_by_teacher' => $gradedCountByTeacher,
            'outstanding_result_classes' => array_map(fn ($labels) => array_values($labels), $outstandingResultClasses),
        ];
    }

    private function teacherClassDutySnapshot(int $schoolId, int $sessionId, int $termId): array
    {
        $scopesByTeacher = [];

        $directClassRows = DB::table('classes')
            ->where('classes.school_id', $schoolId)
            ->where('classes.academic_session_id', $sessionId)
            ->whereNotNull('classes.class_teacher_user_id')
            ->get(['classes.id', 'classes.name', 'classes.class_teacher_user_id']);

        foreach ($directClassRows as $row) {
            $teacherId = (int) ($row->class_teacher_user_id ?? 0);
            $classId = (int) ($row->id ?? 0);
            if ($teacherId < 1 || $classId < 1) {
                continue;
            }

            $scopesByTeacher[$teacherId] = $scopesByTeacher[$teacherId] ?? [];
            $scopesByTeacher[$teacherId][$classId] = [
                'class_name' => (string) ($row->name ?? '-'),
                'full_class' => true,
                'department_ids' => [],
            ];
        }

        if (
            Schema::hasTable('class_departments')
            && Schema::hasColumn('class_departments', 'class_teacher_user_id')
        ) {
            $departmentRows = DB::table('class_departments')
                ->join('classes', 'classes.id', '=', 'class_departments.class_id')
                ->where('class_departments.school_id', $schoolId)
                ->where('classes.school_id', $schoolId)
                ->where('classes.academic_session_id', $sessionId)
                ->whereNotNull('class_departments.class_teacher_user_id')
                ->get([
                    'class_departments.class_id',
                    'class_departments.id as department_id',
                    'class_departments.class_teacher_user_id',
                    'classes.name as class_name',
                ]);

            foreach ($departmentRows as $row) {
                $teacherId = (int) ($row->class_teacher_user_id ?? 0);
                $classId = (int) ($row->class_id ?? 0);
                $departmentId = (int) ($row->department_id ?? 0);
                if ($teacherId < 1 || $classId < 1 || $departmentId < 1) {
                    continue;
                }

                $scopesByTeacher[$teacherId] = $scopesByTeacher[$teacherId] ?? [];
                $existing = $scopesByTeacher[$teacherId][$classId] ?? [
                    'class_name' => (string) ($row->class_name ?? '-'),
                    'full_class' => false,
                    'department_ids' => [],
                ];

                if (!$existing['full_class']) {
                    $existing['department_ids'][] = $departmentId;
                    $existing['department_ids'] = array_values(array_unique(array_map('intval', $existing['department_ids'])));
                }

                $scopesByTeacher[$teacherId][$classId] = $existing;
            }
        }

        if (empty($scopesByTeacher)) {
            return [
                'teacher_ids' => [],
                'comment_preview_by_teacher' => [],
                'outstanding_attendance_classes' => [],
                'outstanding_comment_classes' => [],
                'outstanding_behaviour_classes' => [],
            ];
        }

        $classIds = collect($scopesByTeacher)
            ->flatMap(fn ($classes) => array_keys($classes))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $enrollmentRows = DB::table('enrollments')
            ->where('term_id', $termId)
            ->whereIn('class_id', $classIds)
            ->when(Schema::hasColumn('enrollments', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->get(['class_id', 'student_id', 'department_id']);

        $studentIdsByClass = [];
        $studentIdsByClassDepartment = [];
        foreach ($enrollmentRows as $row) {
            $classId = (int) ($row->class_id ?? 0);
            $studentId = (int) ($row->student_id ?? 0);
            $departmentId = isset($row->department_id) ? (int) $row->department_id : 0;
            if ($classId < 1 || $studentId < 1) {
                continue;
            }

            $studentIdsByClass[$classId] = $studentIdsByClass[$classId] ?? [];
            $studentIdsByClass[$classId][$studentId] = true;

            if ($departmentId > 0) {
                $studentIdsByClassDepartment[$classId] = $studentIdsByClassDepartment[$classId] ?? [];
                $studentIdsByClassDepartment[$classId][$departmentId] = $studentIdsByClassDepartment[$classId][$departmentId] ?? [];
                $studentIdsByClassDepartment[$classId][$departmentId][$studentId] = true;
            }
        }

        $attendanceRows = Schema::hasTable('student_attendances')
            ? DB::table('student_attendances')
                ->where('school_id', $schoolId)
                ->where('term_id', $termId)
                ->whereIn('class_id', $classIds)
                ->get(['class_id', 'student_id', 'comment'])
            : collect();
        $attendanceMap = [];
        foreach ($attendanceRows as $row) {
            $classId = (int) ($row->class_id ?? 0);
            $studentId = (int) ($row->student_id ?? 0);
            if ($classId < 1 || $studentId < 1) {
                continue;
            }
            $attendanceMap[$classId] = $attendanceMap[$classId] ?? [];
            $attendanceMap[$classId][$studentId] = trim((string) ($row->comment ?? ''));
        }

        $behaviourRows = Schema::hasTable('student_behaviour_ratings')
            ? DB::table('student_behaviour_ratings')
                ->where('school_id', $schoolId)
                ->where('term_id', $termId)
                ->whereIn('class_id', $classIds)
                ->get(['class_id', 'student_id'])
            : collect();
        $behaviourMap = [];
        foreach ($behaviourRows as $row) {
            $classId = (int) ($row->class_id ?? 0);
            $studentId = (int) ($row->student_id ?? 0);
            if ($classId < 1 || $studentId < 1) {
                continue;
            }
            $behaviourMap[$classId] = $behaviourMap[$classId] ?? [];
            $behaviourMap[$classId][$studentId] = true;
        }

        $attendanceSettingClassIds = Schema::hasTable('term_attendance_settings')
            ? DB::table('term_attendance_settings')
                ->where('school_id', $schoolId)
                ->where('term_id', $termId)
                ->whereIn('class_id', $classIds)
                ->pluck('class_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
        $attendanceSettingLookup = array_fill_keys($attendanceSettingClassIds, true);

        $teacherIds = [];
        $commentPreviewByTeacher = [];
        $outstandingAttendanceClasses = [];
        $outstandingCommentClasses = [];
        $outstandingBehaviourClasses = [];

        foreach ($scopesByTeacher as $teacherId => $classScopes) {
            $teacherId = (int) $teacherId;
            if ($teacherId < 1) {
                continue;
            }
            $teacherIds[$teacherId] = true;

            $commentSamples = [];
            foreach ($classScopes as $classId => $scope) {
                $classId = (int) $classId;
                $className = (string) ($scope['class_name'] ?? '-');
                $eligibleStudentIds = $this->teacherScopeStudentIds(
                    $classId,
                    (bool) ($scope['full_class'] ?? false),
                    (array) ($scope['department_ids'] ?? []),
                    $studentIdsByClass,
                    $studentIdsByClassDepartment
                );

                if (empty($eligibleStudentIds)) {
                    continue;
                }

                $hasAttendanceSetting = !empty($attendanceSettingLookup[$classId]);
                $missingAttendance = !$hasAttendanceSetting;
                $missingComment = false;
                $missingBehaviour = false;

                foreach ($eligibleStudentIds as $studentId) {
                    $attendanceComment = $attendanceMap[$classId][$studentId] ?? null;
                    if ($attendanceComment === null) {
                        $missingAttendance = true;
                        $missingComment = true;
                    } elseif ($attendanceComment !== '') {
                        $commentSamples[] = $attendanceComment;
                    } else {
                        $missingComment = true;
                    }

                    if (empty($behaviourMap[$classId][$studentId])) {
                        $missingBehaviour = true;
                    }
                }

                if ($missingAttendance) {
                    $outstandingAttendanceClasses[$teacherId] = $outstandingAttendanceClasses[$teacherId] ?? [];
                    $outstandingAttendanceClasses[$teacherId][$className] = $className;
                }
                if ($missingComment) {
                    $outstandingCommentClasses[$teacherId] = $outstandingCommentClasses[$teacherId] ?? [];
                    $outstandingCommentClasses[$teacherId][$className] = $className;
                }
                if ($missingBehaviour) {
                    $outstandingBehaviourClasses[$teacherId] = $outstandingBehaviourClasses[$teacherId] ?? [];
                    $outstandingBehaviourClasses[$teacherId][$className] = $className;
                }
            }

            $commentPreviewByTeacher[$teacherId] = $this->summarizeStoredComments($commentSamples);
        }

        return [
            'teacher_ids' => array_map('intval', array_keys($teacherIds)),
            'comment_preview_by_teacher' => $commentPreviewByTeacher,
            'outstanding_attendance_classes' => array_map(fn ($labels) => array_values($labels), $outstandingAttendanceClasses),
            'outstanding_comment_classes' => array_map(fn ($labels) => array_values($labels), $outstandingCommentClasses),
            'outstanding_behaviour_classes' => array_map(fn ($labels) => array_values($labels), $outstandingBehaviourClasses),
        ];
    }

    private function teacherScopeStudentIds(
        int $classId,
        bool $fullClass,
        array $departmentIds,
        array $studentIdsByClass,
        array $studentIdsByClassDepartment
    ): array {
        if ($fullClass) {
            return array_map('intval', array_keys($studentIdsByClass[$classId] ?? []));
        }

        $studentIds = [];
        foreach ($departmentIds as $departmentId) {
            foreach (array_keys($studentIdsByClassDepartment[$classId][(int) $departmentId] ?? []) as $studentId) {
                $studentIds[(int) $studentId] = true;
            }
        }

        return array_map('intval', array_keys($studentIds));
    }

    private function summarizeStoredComments(array $comments): string
    {
        $uniqueComments = collect($comments)
            ->map(fn ($comment) => trim((string) $comment))
            ->filter()
            ->unique(fn ($comment) => strtolower($comment))
            ->values();

        if ($uniqueComments->isEmpty()) {
            return '-';
        }

        $summary = $uniqueComments
            ->take(2)
            ->map(fn ($comment) => Str::limit($comment, 70, '...'))
            ->implode(' | ');

        if ($uniqueComments->count() > 2) {
            $summary .= ' | ...';
        }

        return $summary;
    }

    private function formatTeacherOutstandingSummary(array $items): string
    {
        $labels = [];

        foreach ((array) ($items['result'] ?? []) as $className) {
            $labels[] = 'Result: ' . $className;
        }
        foreach ((array) ($items['attendance'] ?? []) as $className) {
            $labels[] = 'Attendance: ' . $className;
        }
        foreach ((array) ($items['comment'] ?? []) as $className) {
            $labels[] = 'Comment: ' . $className;
        }
        foreach ((array) ($items['behaviour'] ?? []) as $className) {
            $labels[] = 'Behaviour: ' . $className;
        }

        return empty($labels) ? 'Completed' : implode(', ', $labels);
    }

    private function studentRows(int $schoolId, int $termId)
    {
        $rawRows = DB::table('results')
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
                'results.id as result_id',
                'results.ca',
                'results.exam',
                'results.created_at as result_created_at',
                'results.updated_at as result_updated_at',
            ])
            ->orderBy('users.name')
            ->get();

        $rows = $rawRows
            ->groupBy(fn ($row) => (int) $row->student_id)
            ->map(function ($rows) {
                $first = $rows->first();
                $gradeCounts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];
                $gradedCount = 0;

                foreach ($rows as $row) {
                    if (!$this->isResultRecordGraded(
                        $row->result_id ?? null,
                        $row->ca ?? null,
                        $row->exam ?? null,
                        $row->result_created_at ?? null,
                        $row->result_updated_at ?? null
                    )) {
                        continue;
                    }

                    $gradedCount++;
                    $band = $this->gradeBandFromScore((int) ($row->ca ?? 0) + (int) ($row->exam ?? 0));
                    $gradeCounts[$band] = (int) ($gradeCounts[$band] ?? 0) + 1;
                }

                return [
                    'student_id' => (int) ($first->student_id ?? 0),
                    'name' => (string) ($first->student_name ?? '-'),
                    'email' => (string) ($first->student_email ?? '-'),
                    'total_graded' => $gradedCount > 0 ? $gradedCount : '-',
                    'grades' => $gradedCount > 0
                        ? $gradeCounts
                        : ['A' => '-', 'B' => '-', 'C' => '-', 'D' => '-', 'E' => '-', 'F' => '-'],
                ];
            })
            ->values()
            ->all();

        usort($rows, function (array $a, array $b) {
            $aCount = is_numeric($a['total_graded']) ? (int) $a['total_graded'] : -1;
            $bCount = is_numeric($b['total_graded']) ? (int) $b['total_graded'] : -1;
            if ($aCount !== $bCount) {
                return $bCount <=> $aCount;
            }
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        foreach ($rows as $index => &$row) {
            $row['sn'] = $index + 1;
        }
        unset($row);

        return collect($rows)->values();
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
        return DB::table('classes')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->select('level')
            ->distinct()
            ->pluck('level')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sessionDepartmentOptions(int $schoolId, int $sessionId, string $level): array
    {
        $fromLevelDepartments = [];
        if (Schema::hasTable('level_departments')) {
            $fromLevelDepartments = DB::table('level_departments')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $sessionId)
                ->where('level', strtolower($level))
                ->orderBy('name')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique(fn ($name) => strtolower($name))
                ->values()
                ->all();
        }

        if (!empty($fromLevelDepartments)) {
            return $fromLevelDepartments;
        }

        if (!Schema::hasTable('class_departments')) {
            return [];
        }

        return DB::table('class_departments')
            ->join('classes', 'classes.id', '=', 'class_departments.class_id')
            ->where('class_departments.school_id', $schoolId)
            ->where('classes.school_id', $schoolId)
            ->where('classes.academic_session_id', $sessionId)
            ->where('classes.level', strtolower($level))
            ->orderBy('class_departments.name')
            ->pluck('class_departments.name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => strtolower($name))
            ->values()
            ->all();
    }

    private function resolveDepartmentFilter(string $requestedDepartment, array $departmentOptions): ?string
    {
        $requestedDepartment = trim($requestedDepartment);
        if ($requestedDepartment === '') {
            return null;
        }

        foreach ($departmentOptions as $department) {
            if (strcasecmp((string) $department, $requestedDepartment) === 0) {
                return (string) $department;
            }
        }

        return null;
    }

    private function sessionClassOptions(
        int $schoolId,
        int $sessionId,
        string $level,
        ?string $departmentName = null
    ): array {
        $query = DB::table('classes')
            ->where('classes.school_id', $schoolId)
            ->where('classes.academic_session_id', $sessionId)
            ->where('classes.level', strtolower($level))
            ->select(['classes.id', 'classes.name', 'classes.level']);

        if ($departmentName !== null && $departmentName !== '' && Schema::hasTable('class_departments')) {
            $query
                ->join('class_departments', function ($join) use ($schoolId, $departmentName) {
                    $join->on('class_departments.class_id', '=', 'classes.id')
                        ->where('class_departments.school_id', '=', $schoolId)
                        ->whereRaw('LOWER(class_departments.name) = ?', [strtolower($departmentName)]);
                })
                ->distinct();
        }

        return $query->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'level' => strtolower((string) $row->level),
                ];
            })
            ->sortBy(fn ($item) => $this->classOrderIndex((string) $item['name']))
            ->values()
            ->all();
    }

    private function resolveClassFilter(int $requestedClassId, array $classOptions): ?int
    {
        if ($requestedClassId <= 0) {
            return null;
        }

        foreach ($classOptions as $classOption) {
            if ((int) ($classOption['id'] ?? 0) === $requestedClassId) {
                return $requestedClassId;
            }
        }

        return null;
    }

    private function buildBroadsheetData(
        int $schoolId,
        int $sessionId,
        string $level,
        ?string $departmentName = null,
        ?int $classId = null,
        string $reportScope = 'annual'
    ): array
    {
        $classesQuery = DB::table('classes')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('level', $level);
        if ($classId !== null && $classId > 0) {
            $classesQuery->where('id', $classId);
        }
        $classes = $classesQuery->get(['id', 'name', 'level']);

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

        $termSelection = $this->resolveBroadsheetTermSelection($schoolId, $sessionId, $reportScope);
        $termIds = $termSelection['term_ids'];

        if (empty($termIds)) {
            return [
                'classes' => $classList->all(),
                'subjects' => [],
                'rows' => [],
                'selected_term_name' => $termSelection['selected_term_name'],
            ];
        }

        $departmentStudentIdSet = null;
        if ($departmentName !== null && $departmentName !== '') {
            $departmentStudentIds = DB::table('enrollments')
                ->join('class_departments', 'class_departments.id', '=', 'enrollments.department_id')
                ->whereIn('enrollments.class_id', $classIds)
                ->whereIn('enrollments.term_id', $termIds)
                ->where('class_departments.school_id', $schoolId)
                ->whereRaw('LOWER(class_departments.name) = ?', [strtolower($departmentName)])
                ->pluck('enrollments.student_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (empty($departmentStudentIds)) {
                return [
                    'classes' => $classList->all(),
                    'subjects' => [],
                    'rows' => [],
                    'selected_term_name' => $termSelection['selected_term_name'],
                ];
            }

            $departmentStudentIdSet = array_fill_keys($departmentStudentIds, true);
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
                'selected_term_name' => $termSelection['selected_term_name'],
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
                $name = (string) ($sample->subject_name ?? '');
                return [
                    'id' => (int) $subjectId,
                    'name' => $name,
                    'header_name' => $this->broadsheetHeaderName($name),
                    'code' => $code,
                    'short_code' => $this->subjectShortCode($name, $code),
                ];
            })
            ->values()
            ->sortBy(fn ($item) => strtoupper((string) ($item['name'] ?? '')))
            ->values();

        $subjectIds = $subjects->pluck('id')->all();
        $subjectKeyById = [];
        foreach ($subjects as $subject) {
            $subjectKeyById[(int) $subject['id']] = (string) $subject['id'];
        }

        $resultRowsQuery = DB::table('results')
            ->where('results.school_id', $schoolId)
            ->whereIn('results.term_subject_id', array_keys($termSubjectMetaById))
            ->select([
                'results.id as result_id',
                'results.student_id',
                'results.term_subject_id',
                'results.ca',
                'results.exam',
                'results.created_at as result_created_at',
                'results.updated_at as result_updated_at',
            ]);
        if ($departmentStudentIdSet !== null) {
            $resultRowsQuery->whereIn('results.student_id', array_keys($departmentStudentIdSet));
        }
        $resultRows = $resultRowsQuery->get();

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
            if ($departmentStudentIdSet !== null && !isset($departmentStudentIdSet[$studentId])) {
                continue;
            }
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
                if ($departmentStudentIdSet !== null && !isset($departmentStudentIdSet[$studentId])) {
                    continue;
                }
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
            if (!$this->isResultRecordGraded(
                $resultRow->result_id ?? null,
                $resultRow->ca ?? null,
                $resultRow->exam ?? null,
                $resultRow->result_created_at ?? null,
                $resultRow->result_updated_at ?? null
            )) {
                continue;
            }

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

            $average = $scoredCount > 0 ? round($total / $scoredCount, 2) : null;
            $rows[] = [
                'student_id' => (int) $studentId,
                'name' => (string) $meta['name'],
                'email' => (string) $meta['email'],
                'class_id' => $classId,
                'class_name' => (string) ($classNameById[$classId] ?? '-'),
                'scores' => $subjectScores,
                'total' => $scoredCount > 0 ? round($total, 2) : null,
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
            if ($a['average'] === null && $b['average'] === null) {
                return strcasecmp((string) $a['name'], (string) $b['name']);
            }
            if ($a['average'] === null) {
                return 1;
            }
            if ($b['average'] === null) {
                return -1;
            }
            if ((float) $a['average'] === (float) $b['average']) {
                return strcasecmp((string) $a['name'], (string) $b['name']);
            }
            return ((float) $b['average']) <=> ((float) $a['average']);
        });

        $positionByStudent = [];
        $previousAverage = null;
        $previousRank = 0;
        foreach ($ranking as $index => $item) {
            if ($item['average'] === null) {
                continue;
            }
            $average = (float) $item['average'];
            $rank = ($previousAverage !== null && abs($average - $previousAverage) < 0.00001)
                ? $previousRank
                : ($index + 1);

            $positionByStudent[(int) $item['student_id']] = $rank;
            $previousAverage = $average;
            $previousRank = $rank;
        }

        foreach ($rows as &$row) {
            if ($row['average'] === null) {
                $row['position'] = null;
                $row['position_label'] = '-';
                continue;
            }
            $position = (int) ($positionByStudent[(int) $row['student_id']] ?? 0);
            $row['position'] = $position;
            $row['position_label'] = $position > 0 ? $this->ordinalPosition($position) : '-';
        }
        unset($row);

        return [
            'classes' => $classList->all(),
            'subjects' => $subjects->values()->all(),
            'rows' => $rows,
            'selected_term_name' => $termSelection['selected_term_name'],
        ];
    }

    private function broadsheetReportScopeOptions(): array
    {
        return [
            ['value' => 'annual', 'label' => 'Annual'],
            ['value' => 'first_term', 'label' => 'First Term'],
            ['value' => 'second_term', 'label' => 'Second Term'],
            ['value' => 'third_term', 'label' => 'Third Term'],
        ];
    }

    private function normalizeBroadsheetReportScope(string $scope): string
    {
        return match (strtolower(trim($scope))) {
            'first_term' => 'first_term',
            'second_term' => 'second_term',
            'third_term' => 'third_term',
            default => 'annual',
        };
    }

    private function broadsheetReportScopeLabel(string $scope): string
    {
        return collect($this->broadsheetReportScopeOptions())
            ->firstWhere('value', $this->normalizeBroadsheetReportScope($scope))['label'] ?? 'Annual';
    }

    private function resolveBroadsheetTermSelection(int $schoolId, int $sessionId, string $reportScope): array
    {
        $terms = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($terms->isEmpty()) {
            return [
                'term_ids' => [],
                'selected_term_name' => null,
            ];
        }

        $normalizedScope = $this->normalizeBroadsheetReportScope($reportScope);
        if ($normalizedScope === 'annual') {
            return [
                'term_ids' => $terms->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'selected_term_name' => 'Annual',
            ];
        }

        $scopeIndexMap = [
            'first_term' => 0,
            'second_term' => 1,
            'third_term' => 2,
        ];
        $aliases = [
            'first_term' => ['firstterm', 'first', '1stterm', 'term1'],
            'second_term' => ['secondterm', 'second', '2ndterm', 'term2'],
            'third_term' => ['thirdterm', 'third', '3rdterm', 'term3'],
        ];

        $matchedTerm = $terms->first(function ($term) use ($normalizedScope, $aliases) {
            $normalizedName = strtolower(preg_replace('/[^a-z0-9]+/', '', (string) ($term->name ?? '')));
            foreach ($aliases[$normalizedScope] ?? [] as $alias) {
                if ($normalizedName === $alias) {
                    return true;
                }
            }
            return false;
        });

        if (!$matchedTerm) {
            $matchedTerm = $terms->values()->get($scopeIndexMap[$normalizedScope] ?? 0);
        }

        if (!$matchedTerm) {
            return [
                'term_ids' => [],
                'selected_term_name' => $this->broadsheetReportScopeLabel($normalizedScope),
            ];
        }

        return [
            'term_ids' => [(int) $matchedTerm->id],
            'selected_term_name' => (string) ($matchedTerm->name ?? $this->broadsheetReportScopeLabel($normalizedScope)),
        ];
    }

    private function isResultRecordGraded(
        $resultId,
        $ca,
        $exam,
        $createdAt,
        $updatedAt
    ): bool {
        if (empty($resultId)) {
            return false;
        }

        $caScore = (int) ($ca ?? 0);
        $examScore = (int) ($exam ?? 0);
        if (($caScore + $examScore) > 0) {
            return true;
        }

        if (empty($createdAt) || empty($updatedAt)) {
            return false;
        }

        $createdTs = strtotime((string) $createdAt);
        $updatedTs = strtotime((string) $updatedAt);

        return $createdTs !== false && $updatedTs !== false && $updatedTs > $createdTs;
    }

    private function gradeBandFromScore(int $total): string
    {
        return match (true) {
            $total >= 70 => 'A',
            $total >= 60 => 'B',
            $total >= 50 => 'C',
            $total >= 40 => 'D',
            $total >= 30 => 'E',
            default => 'F',
        };
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

    private function broadsheetHeaderName(string $subjectName): string
    {
        $normalized = trim((string) (preg_replace('/\s+/', ' ', $subjectName) ?? ''));
        if ($normalized === '') {
            return '-';
        }

        $words = preg_split('/\s+/', $normalized) ?: [];
        $isLongMultiWord = count($words) >= 2 && mb_strlen($normalized) > 19;
        if (!$isLongMultiWord) {
            return strtoupper($normalized);
        }

        $parts = array_map(function ($word) {
            $token = trim((string) $word);
            if ($token === '') {
                return '';
            }
            return mb_strlen($token) > 3 ? mb_substr($token, 0, 3) : $token;
        }, $words);

        $parts = array_values(array_filter($parts, fn ($v) => $v !== ''));
        if (empty($parts)) {
            return strtoupper($normalized);
        }

        return strtoupper(implode(' ', $parts));
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
