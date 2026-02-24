<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentBehaviourRating;
use App\Models\Term;
use App\Models\TermAttendanceSetting;
use App\Models\TermSubject;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TranscriptController extends Controller
{
    public function options(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $sessions = $this->sessionsWithTerms($schoolId);
        $selectedSession = $sessions->firstWhere('status', 'current') ?: $sessions->first();

        return response()->json([
            'data' => [
                'sessions' => $this->sessionsPayload($sessions),
                'selected_session_id' => $selectedSession?->id,
            ],
        ]);
    }

    public function show(Request $request)
    {
        $payload = $request->validate([
            'email' => 'required|email',
            'academic_session_id' => 'nullable|integer',
            'scope' => 'nullable|in:single,all',
            'term_id' => 'nullable|integer',
        ]);

        $scope = (string) ($payload['scope'] ?? 'single');
        $email = strtolower(trim((string) $payload['email']));
        $schoolId = (int) $request->user()->school_id;
        $school = $request->user()->school()->first();

        $sessions = $this->sessionsWithTerms($schoolId);
        if ($sessions->isEmpty()) {
            return response()->json([
                'message' => 'No academic session configured for this school.',
            ], 422);
        }

        $session = $this->resolveSession($sessions, (int) ($payload['academic_session_id'] ?? 0));
        if (!$session) {
            return response()->json([
                'message' => 'Selected academic session was not found.',
            ], 422);
        }

        $terms = $this->resolveTerms(
            $session->terms ?? collect(),
            $scope,
            (int) ($payload['term_id'] ?? 0)
        );

        if ($terms->isEmpty()) {
            return response()->json([
                'message' => 'No term is available for the selected session.',
            ], 422);
        }

        $resolved = $this->resolveStudent($schoolId, $email);
        if (!$resolved) {
            return response()->json([
                'message' => 'No student found for the supplied email address.',
            ], 404);
        }

        [$studentUser, $student] = $resolved;

        $entries = $this->buildTranscriptEntries(
            $schoolId,
            $session,
            $terms,
            $student,
            $studentUser,
            $school,
            false
        );

        if (empty($entries)) {
            return response()->json([
                'data' => [],
                'context' => $this->contextPayload($sessions, $session, $terms, $scope, $studentUser),
                'message' => 'No result records found for the selected criteria.',
            ]);
        }

        $data = array_map(function (array $entry) {
            unset($entry['view_data']);
            return $entry;
        }, $entries);

        return response()->json([
            'data' => $data,
            'context' => $this->contextPayload($sessions, $session, $terms, $scope, $studentUser),
        ]);
    }

    public function download(Request $request)
    {
        $payload = $request->validate([
            'email' => 'required|email',
            'academic_session_id' => 'nullable|integer',
            'scope' => 'nullable|in:single,all',
            'term_id' => 'nullable|integer',
        ]);

        $scope = (string) ($payload['scope'] ?? 'single');
        $email = strtolower(trim((string) $payload['email']));
        $schoolId = (int) $request->user()->school_id;
        $school = $request->user()->school()->first();

        $sessions = $this->sessionsWithTerms($schoolId);
        if ($sessions->isEmpty()) {
            return response()->json([
                'message' => 'No academic session configured for this school.',
            ], 422);
        }

        $session = $this->resolveSession($sessions, (int) ($payload['academic_session_id'] ?? 0));
        if (!$session) {
            return response()->json([
                'message' => 'Selected academic session was not found.',
            ], 422);
        }

        $terms = $this->resolveTerms(
            $session->terms ?? collect(),
            $scope,
            (int) ($payload['term_id'] ?? 0)
        );

        if ($terms->isEmpty()) {
            return response()->json([
                'message' => 'No term is available for the selected session.',
            ], 422);
        }

        $resolved = $this->resolveStudent($schoolId, $email);
        if (!$resolved) {
            return response()->json([
                'message' => 'No student found for the supplied email address.',
            ], 404);
        }

        [$studentUser, $student] = $resolved;

        $entries = $this->buildTranscriptEntries(
            $schoolId,
            $session,
            $terms,
            $student,
            $studentUser,
            $school,
            true
        );

        if (empty($entries)) {
            return response()->json([
                'message' => 'No result records found for the selected criteria.',
            ], 404);
        }

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $pdfOutput = $this->renderTranscriptPdfFromEntries($entries);

            $safeStudent = Str::slug((string) ($studentUser->name ?: 'student'));
            $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));
            $scopeSuffix = $scope === 'all'
                ? 'all_terms'
                : Str::slug((string) (($entries[0]['term']['name'] ?? 'term')));
            $filename = "{$safeStudent}_{$safeSession}_{$scopeSuffix}_transcript.pdf";

            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Throwable $e) {
            Log::warning('Transcript PDF generation failed with embedded assets, retrying without assets', [
                'school_id' => $schoolId,
                'student_user_id' => $studentUser->id ?? null,
                'session_id' => $session->id ?? null,
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);

            try {
                $entriesNoAssets = $this->buildTranscriptEntries(
                    $schoolId,
                    $session,
                    $terms,
                    $student,
                    $studentUser,
                    $school,
                    false
                );

                if (empty($entriesNoAssets)) {
                    return response()->json([
                        'message' => 'No result records found for the selected criteria.',
                    ], 404);
                }

                $pdfOutput = $this->renderTranscriptPdfFromEntries($entriesNoAssets);
                $safeStudent = Str::slug((string) ($studentUser->name ?: 'student'));
                $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));
                $scopeSuffix = $scope === 'all'
                    ? 'all_terms'
                    : Str::slug((string) (($entriesNoAssets[0]['term']['name'] ?? 'term')));
                $filename = "{$safeStudent}_{$safeSession}_{$scopeSuffix}_transcript.pdf";

                return response($pdfOutput, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            } catch (Throwable $fallbackError) {
                Log::warning('Transcript PDF generation failed after no-asset fallback, trying simplified transcript renderer', [
                    'school_id' => $schoolId,
                    'student_user_id' => $studentUser->id ?? null,
                    'session_id' => $session->id ?? null,
                    'scope' => $scope,
                    'error' => $fallbackError->getMessage(),
                ]);

                try {
                    $simplePdfOutput = $this->renderSimpleTranscriptPdfFromEntries($entriesNoAssets);
                    $safeStudent = Str::slug((string) ($studentUser->name ?: 'student'));
                    $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));
                    $scopeSuffix = $scope === 'all'
                        ? 'all_terms'
                        : Str::slug((string) (($entriesNoAssets[0]['term']['name'] ?? 'term')));
                    $filename = "{$safeStudent}_{$safeSession}_{$scopeSuffix}_transcript.pdf";

                    return response($simplePdfOutput, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    ]);
                } catch (Throwable $simpleFallbackError) {
                    Log::error('Transcript PDF generation failed after simplified fallback', [
                        'school_id' => $schoolId,
                        'student_user_id' => $studentUser->id ?? null,
                        'session_id' => $session->id ?? null,
                        'scope' => $scope,
                        'error' => $simpleFallbackError->getMessage(),
                    ]);

                    return response()->json([
                        'message' => 'Unable to generate transcript PDF. Please check student/session data and branding images.',
                    ], 500);
                }
            }
        }
    }

    private function renderTranscriptPdfFromEntries(array $entries): string
    {
        $htmlParts = [];
        $entryCount = count($entries);
        foreach ($entries as $index => $entry) {
            $viewData = $entry['view_data'];
            $viewData['embedded'] = true;
            $htmlParts[] = view('pdf.student_result', $viewData)->render();
            if ($index < $entryCount - 1) {
                $htmlParts[] = '<div style="page-break-after: always;"></div>';
            }
        }

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Student Transcript</title></head><body>'
            . implode("\n", $htmlParts)
            . '</body></html>';
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

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

        return $dompdf->output();
    }

    private function renderSimpleTranscriptPdfFromEntries(array $entries): string
    {
        $pages = [];
        $count = count($entries);
        foreach ($entries as $index => $entry) {
            $viewData = $entry['view_data'] ?? [];
            $schoolName = strtoupper((string) data_get($viewData, 'school.name', 'SCHOOL'));
            $schoolLocation = strtoupper((string) data_get($viewData, 'school.location', ''));
            $studentName = strtoupper((string) data_get($viewData, 'studentUser.name', '-'));
            $studentSerial = strtoupper((string) data_get($viewData, 'studentUser.username', '-'));
            $className = strtoupper((string) data_get($viewData, 'class.name', '-'));
            $termName = strtoupper((string) data_get($viewData, 'term.name', '-'));
            $sessionName = strtoupper((string) (data_get($viewData, 'session.academic_year') ?: data_get($viewData, 'session.session_name', '-')));
            $teacherComment = strtoupper((string) data_get($viewData, 'teacherComment', '-'));
            $headComment = strtoupper((string) data_get($viewData, 'schoolHeadComment', '-'));
            $average = number_format((float) data_get($viewData, 'averageScore', 0), 2);
            $total = (int) data_get($viewData, 'totalScore', 0);
            $rows = (array) data_get($viewData, 'rows', []);

            $rowsHtml = '';
            foreach ($rows as $row) {
                $subject = strtoupper((string) ($row['subject_name'] ?? '-'));
                $ca = (int) ($row['ca'] ?? 0);
                $exam = (int) ($row['exam'] ?? 0);
                $score = (int) ($row['total'] ?? 0);
                $grade = strtoupper((string) ($row['grade'] ?? '-'));
                $remark = strtoupper((string) ($row['remark'] ?? '-'));

                $rowsHtml .= '<tr>'
                    . '<td>' . e($subject) . '</td>'
                    . '<td style="text-align:center;">' . $ca . '</td>'
                    . '<td style="text-align:center;">' . $exam . '</td>'
                    . '<td style="text-align:center;">' . $score . '</td>'
                    . '<td style="text-align:center;">' . e($grade) . '</td>'
                    . '<td>' . e($remark) . '</td>'
                    . '</tr>';
            }

            if ($rowsHtml === '') {
                $rowsHtml = '<tr><td colspan="6" style="text-align:center;">No result data found.</td></tr>';
            }

            $pageHtml = '<div class="page">'
                . '<h1>' . e($schoolName) . '</h1>'
                . '<h2>' . e($schoolLocation) . '</h2>'
                . '<h3>TRANSCRIPT FOR ' . e($termName) . ' - ' . e($sessionName) . '</h3>'
                . '<table class="meta">'
                . '<tr><th style="width:20%;">Student</th><td style="width:30%;">' . e($studentName) . '</td><th style="width:20%;">Serial No</th><td style="width:30%;">' . e($studentSerial) . '</td></tr>'
                . '<tr><th>Class</th><td>' . e($className) . '</td><th>Average</th><td>' . e($average) . '</td></tr>'
                . '<tr><th>Total Score</th><td>' . $total . '</td><th>Term</th><td>' . e($termName) . '</td></tr>'
                . '</table>'
                . '<table>'
                . '<thead><tr><th style="width:35%;">Subject</th><th style="width:10%;">CA</th><th style="width:10%;">Exam</th><th style="width:10%;">Total</th><th style="width:10%;">Grade</th><th style="width:25%;">Remark</th></tr></thead>'
                . '<tbody>' . $rowsHtml . '</tbody>'
                . '</table>'
                . '<table class="meta">'
                . '<tr><th style="width:24%;">School Head Comment</th><td>' . e($headComment) . '</td></tr>'
                . '<tr><th>Class Teacher Comment</th><td>' . e($teacherComment) . '</td></tr>'
                . '</table>'
                . '</div>';

            $pages[] = $pageHtml;
            if ($index < $count - 1) {
                $pages[] = '<div style="page-break-after: always;"></div>';
            }
        }

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Transcript</title>'
            . '<style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10px;color:#111;}'
            . 'h1{margin:0;font-size:18px;text-align:center;}'
            . 'h2{margin:4px 0 8px 0;font-size:12px;text-align:center;}'
            . 'h3{margin:0 0 8px 0;font-size:11px;text-align:center;font-weight:600;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:8px;}'
            . 'th,td{border:1px solid #222;padding:4px;}'
            . 'th{background:#f3f4f6;text-align:left;}'
            . '.meta td,.meta th{font-size:10px;}'
            . '</style></head><body>'
            . implode("\n", $pages)
            . '</body></html>';

        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

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

        return $dompdf->output();
    }

    private function sessionsWithTerms(int $schoolId): Collection
    {
        $sessions = AcademicSession::where('school_id', $schoolId)
            ->orderByDesc('id')
            ->get(['id', 'session_name', 'academic_year', 'status']);

        if ($sessions->isEmpty()) {
            return collect();
        }

        $termsBySession = Term::where('school_id', $schoolId)
            ->whereIn('academic_session_id', $sessions->pluck('id')->all())
            ->orderBy('id')
            ->get(['id', 'academic_session_id', 'name', 'is_current'])
            ->groupBy('academic_session_id');

        return $sessions->map(function (AcademicSession $session) use ($termsBySession) {
            $session->terms = $termsBySession->get($session->id, collect());
            return $session;
        });
    }

    private function sessionsPayload(Collection $sessions): array
    {
        return $sessions->map(function (AcademicSession $session) {
            return [
                'id' => (int) $session->id,
                'session_name' => $session->session_name,
                'academic_year' => $session->academic_year,
                'status' => $session->status,
                'is_current' => $session->status === 'current',
                'terms' => collect($session->terms ?? [])->map(function ($term) {
                    return [
                        'id' => (int) $term->id,
                        'name' => $term->name,
                        'is_current' => (bool) $term->is_current,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    private function resolveSession(Collection $sessions, int $requestedSessionId): ?AcademicSession
    {
        if ($requestedSessionId > 0) {
            $selected = $sessions->firstWhere('id', $requestedSessionId);
            if ($selected) {
                return $selected;
            }
        }

        return $sessions->firstWhere('status', 'current') ?: $sessions->first();
    }

    private function resolveTerms(Collection $sessionTerms, string $scope, int $requestedTermId): Collection
    {
        if ($scope === 'all') {
            return $sessionTerms->values();
        }

        if ($requestedTermId > 0) {
            $selected = $sessionTerms->firstWhere('id', $requestedTermId);
            if ($selected) {
                return collect([$selected]);
            }
        }

        $fallback = $sessionTerms->firstWhere('is_current', true) ?: $sessionTerms->first();
        return $fallback ? collect([$fallback]) : collect();
    }

    private function resolveStudent(int $schoolId, string $email): ?array
    {
        $studentUser = User::where('school_id', $schoolId)
            ->where('role', 'student')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first(['id', 'name', 'email', 'username', 'school_id', 'photo_path']);

        if (!$studentUser) {
            return null;
        }

        $student = Student::where('school_id', $schoolId)
            ->where('user_id', $studentUser->id)
            ->first();

        if (!$student) {
            return null;
        }

        return [$studentUser, $student];
    }

    private function contextPayload(
        Collection $sessions,
        AcademicSession $selectedSession,
        Collection $selectedTerms,
        string $scope,
        User $studentUser
    ): array {
        $selectedTerm = $selectedTerms->count() === 1 ? $selectedTerms->first() : null;

        return [
            'student' => [
                'id' => (int) $studentUser->id,
                'name' => $studentUser->name,
                'email' => $studentUser->email,
                'username' => $studentUser->username,
            ],
            'scope' => $scope,
            'selected_session' => [
                'id' => (int) $selectedSession->id,
                'session_name' => $selectedSession->session_name,
                'academic_year' => $selectedSession->academic_year,
            ],
            'selected_term' => $selectedTerm ? [
                'id' => (int) $selectedTerm->id,
                'name' => $selectedTerm->name,
            ] : null,
            'sessions' => $this->sessionsPayload($sessions),
        ];
    }

    private function buildTranscriptEntries(
        int $schoolId,
        AcademicSession $session,
        Collection $terms,
        Student $student,
        User $studentUser,
        $school,
        bool $withEmbeddedAssets = true
    ): array {
        $entries = [];
        $studentPhotoPath = $student?->photo_path ?: $studentUser?->photo_path;

        foreach ($terms as $term) {
            $classId = $this->resolveClassIdForTerm(
                $schoolId,
                (int) $session->id,
                (int) $term->id,
                (int) $student->id
            );
            if (!$classId) {
                continue;
            }

            $class = SchoolClass::where('id', $classId)
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->first();
            if (!$class) {
                continue;
            }

            $rows = $this->subjectRows($schoolId, (int) $class->id, (int) $term->id, (int) $student->id);

            $behaviour = StudentBehaviourRating::where('school_id', $schoolId)
                ->where('class_id', $class->id)
                ->where('term_id', $term->id)
                ->where('student_id', $student->id)
                ->first();

            $attendance = StudentAttendance::where('school_id', $schoolId)
                ->where('class_id', $class->id)
                ->where('term_id', $term->id)
                ->where('student_id', $student->id)
                ->first();

            $attendanceSetting = TermAttendanceSetting::where('school_id', $schoolId)
                ->where('class_id', $class->id)
                ->where('term_id', $term->id)
                ->first();

            $classTeacher = null;
            if ($class->class_teacher_user_id) {
                $classTeacher = User::where('id', $class->class_teacher_user_id)
                    ->where('school_id', $schoolId)
                    ->first(['id', 'name', 'email']);
            }

            $totalScore = (int) collect($rows)->sum('total');
            $subjectCount = max(1, count($rows));
            $averageScore = (float) round($totalScore / $subjectCount, 2);
            $overallGrade = $this->gradeFromTotal((int) round($averageScore));

            $teacherComment = (string) ($behaviour?->teacher_comment ?? '');
            if ($teacherComment === '') {
                $teacherComment = (string) ($attendance?->comment ?? '');
            }
            if ($teacherComment === '') {
                $teacherComment = $this->defaultTeacherComment($overallGrade);
            }
            $schoolHeadComment = $this->defaultHeadComment($overallGrade);

            $behaviourTraits = [
                ['label' => 'Handwriting', 'value' => (int) ($behaviour?->handwriting ?? 0)],
                ['label' => 'Speech', 'value' => (int) ($behaviour?->speech ?? 0)],
                ['label' => 'Attitude', 'value' => (int) ($behaviour?->attitude ?? 0)],
                ['label' => 'Reading', 'value' => (int) ($behaviour?->reading ?? 0)],
                ['label' => 'Punctuality', 'value' => (int) ($behaviour?->punctuality ?? 0)],
                ['label' => 'Teamwork', 'value' => (int) ($behaviour?->teamwork ?? 0)],
                ['label' => 'Self Control', 'value' => (int) ($behaviour?->self_control ?? 0)],
            ];

            $viewData = [
                'school' => $school,
                'session' => $session,
                'term' => $term,
                'class' => $class,
                'student' => $student,
                'studentUser' => $studentUser,
                'rows' => $rows,
                'totalScore' => $totalScore,
                'averageScore' => $averageScore,
                'overallGrade' => $overallGrade,
                'attendance' => $attendance,
                'attendanceSetting' => $attendanceSetting,
                'nextTermBeginDate' => $attendanceSetting?->next_term_begin_date,
                'teacherComment' => $teacherComment,
                'schoolHeadComment' => $schoolHeadComment,
                'classTeacher' => $classTeacher,
                'behaviourTraits' => $behaviourTraits,
                'schoolLogoDataUri' => $withEmbeddedAssets ? $this->toDataUri($school?->logo_path) : null,
                'studentPhotoDataUri' => $withEmbeddedAssets ? $this->toDataUri($studentPhotoPath) : null,
                'headSignatureDataUri' => $withEmbeddedAssets ? $this->toDataUri($school?->head_signature_path) : null,
            ];

            $entries[] = [
                'session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'term' => [
                    'id' => (int) $term->id,
                    'name' => $term->name,
                    'is_current' => (bool) $term->is_current,
                ],
                'class' => [
                    'id' => (int) $class->id,
                    'name' => $class->name,
                    'level' => $class->level,
                ],
                'summary' => [
                    'subjects_count' => count($rows),
                    'total_score' => $totalScore,
                    'average_score' => $averageScore,
                    'overall_grade' => $overallGrade,
                ],
                'rows' => $rows,
                'view_data' => $viewData,
            ];
        }

        return $entries;
    }

    private function resolveClassIdForTerm(
        int $schoolId,
        int $sessionId,
        int $termId,
        int $studentId
    ): ?int {
        $classFromResults = DB::table('results')
            ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
            ->where('results.school_id', $schoolId)
            ->where('results.student_id', $studentId)
            ->where('term_subjects.term_id', $termId)
            ->when(Schema::hasColumn('term_subjects', 'school_id'), function ($q) use ($schoolId) {
                $q->where('term_subjects.school_id', $schoolId);
            })
            ->orderByDesc('results.id')
            ->value('term_subjects.class_id');

        if ($classFromResults) {
            return (int) $classFromResults;
        }

        $classFromClassStudents = DB::table('class_students')
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->orderByDesc('id')
            ->value('class_id');

        if ($classFromClassStudents) {
            return (int) $classFromClassStudents;
        }

        $classFromEnrollmentTerm = Enrollment::query()
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.term_id', $termId)
            ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
                $q->where('enrollments.school_id', $schoolId);
            })
            ->orderByDesc('enrollments.id')
            ->value('enrollments.class_id');

        if ($classFromEnrollmentTerm) {
            return (int) $classFromEnrollmentTerm;
        }

        $classFromEnrollmentSession = Enrollment::query()
            ->where('enrollments.student_id', $studentId)
            ->join('terms', 'terms.id', '=', 'enrollments.term_id')
            ->where('terms.academic_session_id', $sessionId)
            ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
                $q->where('enrollments.school_id', $schoolId);
            })
            ->orderByDesc('enrollments.id')
            ->value('enrollments.class_id');

        return $classFromEnrollmentSession ? (int) $classFromEnrollmentSession : null;
    }

    private function subjectRows(int $schoolId, int $classId, int $termId, int $studentId): array
    {
        $subjects = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.class_id', $classId)
            ->where('term_subjects.term_id', $termId)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->leftJoin('results', function ($join) use ($studentId) {
                $join->on('results.term_subject_id', '=', 'term_subjects.id')
                    ->where('results.student_id', '=', $studentId);
            })
            ->select([
                'term_subjects.id as term_subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
                'results.student_id as result_student_id',
                'results.ca',
                'results.exam',
            ])
            ->orderBy('subjects.name')
            ->get();

        $termSubjectIds = $subjects->pluck('term_subject_id')->map(fn ($id) => (int) $id)->all();
        $subjectStats = $this->buildSubjectStats($schoolId, $termSubjectIds);

        return $subjects
            ->map(function ($r) use ($subjectStats, $studentId) {
                $ca = (int) ($r->ca ?? 0);
                $exam = (int) ($r->exam ?? 0);
                $total = $ca + $exam;
                $termSubjectId = (int) $r->term_subject_id;
                $stats = $subjectStats[$termSubjectId] ?? null;
                $position = $stats['positions'][$studentId] ?? null;

                return [
                    'term_subject_id' => $termSubjectId,
                    'subject_name' => $r->subject_name,
                    'subject_code' => $r->subject_code,
                    'ca' => $ca,
                    'exam' => $exam,
                    'total' => $total,
                    'min_score' => $stats['min_score'] ?? 0,
                    'max_score' => $stats['max_score'] ?? 0,
                    'class_average' => $stats['class_average'] ?? 0,
                    'position' => $position,
                    'position_label' => $position ? $this->ordinalPosition($position) : '-',
                    'grade' => $this->gradeFromTotal($total),
                    'remark' => $this->remarkFromTotal($total),
                ];
            })
            ->values()
            ->all();
    }

    private function buildSubjectStats(int $schoolId, array $termSubjectIds): array
    {
        if (empty($termSubjectIds)) {
            return [];
        }

        $rows = DB::table('results')
            ->where('school_id', $schoolId)
            ->whereIn('term_subject_id', $termSubjectIds)
            ->select(['term_subject_id', 'student_id', 'ca', 'exam'])
            ->get();

        $grouped = $rows->groupBy(fn ($r) => (int) $r->term_subject_id);
        $stats = [];

        foreach ($grouped as $termSubjectId => $subjectRows) {
            $totals = $subjectRows
                ->map(fn ($row) => [
                    'student_id' => (int) $row->student_id,
                    'total' => (int) $row->ca + (int) $row->exam,
                ])
                ->values();

            if ($totals->isEmpty()) {
                $stats[(int) $termSubjectId] = [
                    'min_score' => 0,
                    'max_score' => 0,
                    'class_average' => 0,
                    'positions' => [],
                ];
                continue;
            }

            $minScore = (int) $totals->min('total');
            $maxScore = (int) $totals->max('total');
            $classAverage = (float) round((float) $totals->avg('total'), 2);

            $sorted = $totals->sortByDesc('total')->values();
            $positions = [];
            $previousTotal = null;
            $previousRank = 0;

            foreach ($sorted as $index => $row) {
                $rank = ($previousTotal !== null && $row['total'] === $previousTotal)
                    ? $previousRank
                    : ($index + 1);

                $positions[$row['student_id']] = $rank;
                $previousTotal = $row['total'];
                $previousRank = $rank;
            }

            $stats[(int) $termSubjectId] = [
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'class_average' => $classAverage,
                'positions' => $positions,
            ];
        }

        return $stats;
    }

    private function gradeFromTotal(int $total): string
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

    private function remarkFromTotal(int $total): string
    {
        return match (true) {
            $total >= 70 => 'EXCELLENT',
            $total >= 60 => 'VERY GOOD',
            $total >= 50 => 'GOOD',
            $total >= 40 => 'FAIR',
            default => 'NEEDS IMPROVEMENT',
        };
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

    private function defaultTeacherComment(string $grade): string
    {
        return match ($grade) {
            'A' => 'Excellent performance. Keep maintaining this standard.',
            'B' => 'Very good result. Keep pushing for excellence.',
            'C' => 'Good effort. More consistency is needed.',
            'D' => 'Fair performance. Needs more attention and practice.',
            'E' => 'Below average performance. Improvement is required.',
            default => 'Poor performance. Immediate intervention is advised.',
        };
    }

    private function defaultHeadComment(string $grade): string
    {
        return match ($grade) {
            'A' => 'Impressive performance. Keep aiming higher and stay focused.',
            'B' => 'Very good result. With more effort, you can reach excellent level.',
            'C' => 'Good progress. Stay consistent and improve in weaker subjects.',
            'D' => 'You can do better. More reading and guidance are needed.',
            'E' => 'Significant improvement is required. Parents and teachers should monitor closely.',
            default => 'Performance is below expectation. Immediate academic support is required.',
        };
    }

    private function toDataUri(?string $storagePath): ?string
    {
        try {
            if (!$storagePath) {
                return null;
            }

            $fullPath = Storage::disk('public')->path($storagePath);
            if (!is_file($fullPath)) {
                return null;
            }

            // Oversized images can break Dompdf on lower-memory servers.
            $size = @filesize($fullPath);
            if (is_int($size) && $size > 700 * 1024) {
                return null;
            }

            $mime = strtolower((string) (mime_content_type($fullPath) ?: ''));
            $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp'];
            if (!in_array($mime, $allowedMimes, true)) {
                return null;
            }

            $binary = @file_get_contents($fullPath);
            if (!is_string($binary) || $binary === '') {
                return null;
            }

            $base64 = base64_encode($binary);

            return "data:{$mime};base64,{$base64}";
        } catch (Throwable $e) {
            Log::warning('Failed to build transcript image data URI', [
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
