<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateStudentResultPdfJob;
use App\Models\AcademicSession;
use App\Models\GeneratedDocument;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentBehaviourRating;
use App\Models\Term;
use App\Models\TermAttendanceSetting;
use App\Models\TermSubject;
use App\Models\User;
use App\Support\AssessmentSchema;
use App\Support\GradingSchema;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResultsController extends Controller
{
    private function resolveClassTeacherForStudentTerm(
        int $schoolId,
        SchoolClass $class,
        int $studentId,
        int $termId
    ): ?User {
        if (!empty($class->class_teacher_user_id)) {
            $teacher = User::where('id', (int) $class->class_teacher_user_id)
                ->where('school_id', $schoolId)
                ->first(['id', 'name', 'email']);
            if ($teacher) {
                return $teacher;
            }
        }

        if (
            !Schema::hasTable('class_departments')
            || !Schema::hasColumn('class_departments', 'class_teacher_user_id')
            || !Schema::hasColumn('enrollments', 'department_id')
        ) {
            return null;
        }

        $enrollmentQuery = Enrollment::query()
            ->where('enrollments.class_id', (int) $class->id)
            ->where('enrollments.term_id', $termId)
            ->where('enrollments.student_id', $studentId);

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollmentQuery->where('enrollments.school_id', $schoolId);
        }

        $departmentId = (int) ($enrollmentQuery
            ->orderByDesc('enrollments.id')
            ->value('enrollments.department_id') ?? 0);

        if ($departmentId > 0) {
            $teacherUserId = DB::table('class_departments')
                ->where('school_id', $schoolId)
                ->where('class_id', (int) $class->id)
                ->where('id', $departmentId)
                ->value('class_teacher_user_id');

            if (!empty($teacherUserId)) {
                $teacher = User::where('id', (int) $teacherUserId)
                    ->where('school_id', $schoolId)
                    ->first(['id', 'name', 'email']);
                if ($teacher) {
                    return $teacher;
                }
            }
        }

        $candidateTeacherIds = DB::table('class_departments')
            ->where('school_id', $schoolId)
            ->where('class_id', (int) $class->id)
            ->whereNotNull('class_teacher_user_id')
            ->distinct()
            ->pluck('class_teacher_user_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->values();

        if ($candidateTeacherIds->count() === 1) {
            return User::where('id', (int) $candidateTeacherIds->first())
                ->where('school_id', $schoolId)
                ->first(['id', 'name', 'email']);
        }

        return null;
    }

    private function currentSessionClassIds(int $schoolId, int $sessionId, int $studentId): array
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
            return array_values(array_unique($classIds));
        }

        // Backward-compatible fallback for schools still using term enrollments.
        $enrollmentsQuery = Enrollment::query()
            ->where('enrollments.student_id', $studentId)
            ->join('terms', 'terms.id', '=', 'enrollments.term_id')
            ->where('terms.academic_session_id', $sessionId);

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $enrollmentsQuery->where('enrollments.school_id', $schoolId);
        }

        return $enrollmentsQuery
            ->distinct()
            ->pluck('enrollments.class_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

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

    // GET /api/student/results/classes
    public function classes(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        if (!$user->school || !$user->school->results_published) {
            return response()->json(['message' => 'Results are not yet published for your school'], 403);
        }

        $schoolId = (int) $user->school_id;

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json(['data' => []]);
        }

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            return response()->json(['data' => []]);
        }

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id);
        if (empty($classIds)) {
            return response()->json(['data' => []]);
        }

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->whereIn('id', $classIds)
            ->orderBy('level')
            ->orderBy('name')
            ->get(['id', 'name', 'level'])
            ->keyBy('id');

        $terms = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'name']);

        $items = [];
        foreach ($classes as $class) {
            foreach ($terms as $term) {
                $items[] = [
                    'class_id' => (int) $class->id,
                    'term_id' => (int) $term->id,
                    'class_name' => $class->name,
                    'class_level' => $class->level,
                    'term_name' => $term->name,
                ];
            }
        }

        return response()->json(['data' => $items]);
    }

    // GET /api/student/results?class_id=1&term_id=2
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        if (!$user->school || !$user->school->results_published) {
            return response()->json(['message' => 'Results are not yet published for your school'], 403);
        }

        $payload = $request->validate([
            'class_id' => 'required|integer',
            'term_id' => 'required|integer',
        ]);

        $schoolId = (int) $user->school_id;
        $classId = (int) $payload['class_id'];
        $termId = (int) $payload['term_id'];

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json(['data' => []]);
        }

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            return response()->json(['data' => []]);
        }

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id);
        if (!in_array($classId, $classIds, true)) {
            return response()->json(['data' => []]);
        }

        $term = Term::where('id', $termId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();
        if (!$term) {
            return response()->json(['data' => []]);
        }

        $rows = $this->subjectRows($schoolId, $classId, $termId, (int) $student->id);

        return response()->json([
            'data' => $rows,
            'assessment_schema' => $this->assessmentSchemaForSchool($schoolId),
        ]);
    }
    // GET /api/student/results/download?class_id=1&term_id=2
    public function download(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        if (!$user->school || !$user->school->results_published) {
            return response()->json(['message' => 'Results are not yet published for your school'], 403);
        }

        $payload = $request->validate([
            'class_id' => 'required|integer',
            'term_id' => 'required|integer',
        ]);

        $schoolId = (int) $user->school_id;
        $classId = (int) $payload['class_id'];
        $termId = (int) $payload['term_id'];

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'No current session found'], 422);
        }

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student record not found'], 404);
        }

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id);
        if (!in_array($classId, $classIds, true)) {
            return response()->json(['message' => 'You are not enrolled in the selected class for this session'], 403);
        }

        $class = SchoolClass::where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $term = Term::where('id', $termId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();
        if (!$term) {
            return response()->json(['message' => 'Term not found'], 404);
        }

        try {
            $generated = $this->buildStudentResultPdfForResolvedStudent(
                $user,
                $user,
                $student,
                $session,
                $term,
                $class,
                $classId,
                $termId
            );

            return response($generated['pdf_output'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $generated['file_name'] . '"',
            ]);
        } catch (Throwable $e) {
            Log::error('Student result PDF generation failed', [
                'school_id' => $schoolId,
                'user_id' => $user->id ?? null,
                'student_id' => $student->id ?? null,
                'class_id' => $classId,
                'term_id' => $termId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to generate result PDF. Please check student/session data and branding images.',
            ], 500);
        }
    }

    // POST /api/student/results/download-jobs
    public function requestDownloadJob(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        if (!$user->school || !$user->school->results_published) {
            return response()->json(['message' => 'Results are not yet published for your school'], 403);
        }

        $payload = $request->validate([
            'class_id' => 'required|integer',
            'term_id' => 'required|integer',
        ]);

        $schoolId = (int) $user->school_id;
        $classId = (int) $payload['class_id'];
        $termId = (int) $payload['term_id'];

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'No current session found'], 422);
        }

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student record not found'], 404);
        }

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id);
        if (!in_array($classId, $classIds, true)) {
            return response()->json(['message' => 'You are not enrolled in the selected class for this session'], 403);
        }

        $class = SchoolClass::where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $term = Term::where('id', $termId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();
        if (!$term) {
            return response()->json(['message' => 'Term not found'], 404);
        }

        $safeStudent = Str::slug((string) ($user->name ?: 'student'));
        $safeTerm = Str::slug((string) ($term->name ?: 'term'));
        $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));

        $document = GeneratedDocument::create([
            'school_id' => $schoolId,
            'requested_by_user_id' => (int) $user->id,
            'type' => 'student_result_pdf',
            'status' => GeneratedDocument::STATUS_PENDING,
            'disk' => 'local',
            'payload' => [
                'class_id' => $classId,
                'term_id' => $termId,
            ],
            'file_name' => "{$safeStudent}_{$safeSession}_{$safeTerm}_result.pdf",
        ]);

        GenerateStudentResultPdfJob::dispatch((int) $document->id);

        return response()->json([
            'message' => 'Result PDF generation started.',
            'data' => $this->generatedDocumentPayload($document),
        ], 202);
    }

    // GET /api/student/results/download-jobs/{generatedDocument}
    public function downloadJobStatus(Request $request, GeneratedDocument $generatedDocument)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        if (
            (int) $generatedDocument->requested_by_user_id !== (int) $user->id
            || (int) $generatedDocument->school_id !== (int) $user->school_id
            || (string) $generatedDocument->type !== 'student_result_pdf'
        ) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $generatedDocument->refresh();

        return response()->json([
            'data' => $this->generatedDocumentPayload($generatedDocument),
        ]);
    }

    // GET /api/student/results/download-jobs/{generatedDocument}/file
    public function downloadGeneratedFile(Request $request, GeneratedDocument $generatedDocument)
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403);

        if (
            (int) $generatedDocument->requested_by_user_id !== (int) $user->id
            || (int) $generatedDocument->school_id !== (int) $user->school_id
            || (string) $generatedDocument->type !== 'student_result_pdf'
        ) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        if ($generatedDocument->status !== GeneratedDocument::STATUS_COMPLETED || !$generatedDocument->file_path) {
            return response()->json(['message' => 'Document is still processing.'], 409);
        }

        $disk = (string) ($generatedDocument->disk ?: 'local');
        if (!Storage::disk($disk)->exists($generatedDocument->file_path)) {
            return response()->json(['message' => 'Generated file not found.'], 404);
        }

        return Storage::disk($disk)->download(
            $generatedDocument->file_path,
            $generatedDocument->file_name ?: 'student_result.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function generateStudentResultPdfDocumentForJob(int $requestingUserId, int $schoolId, int $classId, int $termId): array
    {
        $user = User::where('id', $requestingUserId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$user || $user->role !== 'student') {
            throw new \RuntimeException('Student user not found for document generation.');
        }

        $school = $user->school ?: $user->school()->first();
        if (!$school || !$school->results_published) {
            throw new \RuntimeException('Results are not published for this school.');
        }

        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();
        if (!$session) {
            throw new \RuntimeException('No current session found.');
        }

        $student = Student::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();
        if (!$student) {
            throw new \RuntimeException('Student record not found.');
        }

        $classIds = $this->currentSessionClassIds($schoolId, (int) $session->id, (int) $student->id);
        if (!in_array($classId, $classIds, true)) {
            throw new \RuntimeException('Student is not enrolled in the selected class for this session.');
        }

        $class = SchoolClass::where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();
        if (!$class) {
            throw new \RuntimeException('Class not found.');
        }

        $term = Term::where('id', $termId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->first();
        if (!$term) {
            throw new \RuntimeException('Term not found.');
        }

        return $this->buildStudentResultPdfForResolvedStudent(
            $user,
            $user,
            $student,
            $session,
            $term,
            $class,
            $classId,
            $termId
        );
    }

    private function generatedDocumentPayload(GeneratedDocument $generatedDocument): array
    {
        return [
            'id' => (int) $generatedDocument->id,
            'type' => (string) $generatedDocument->type,
            'status' => (string) $generatedDocument->status,
            'file_name' => $generatedDocument->file_name,
            'error_message' => $generatedDocument->error_message,
            'can_download' => $generatedDocument->status === GeneratedDocument::STATUS_COMPLETED && !empty($generatedDocument->file_path),
            'created_at' => optional($generatedDocument->created_at)->toIso8601String(),
            'started_at' => optional($generatedDocument->started_at)->toIso8601String(),
            'completed_at' => optional($generatedDocument->completed_at)->toIso8601String(),
        ];
    }

    // GET /api/school-admin/reports/student-result/download?student=...&academic_session_id=...&term_id=...
    public function downloadForSchoolAdmin(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->role === 'school_admin', 403);

        if (!$actor->school || !$actor->school->results_published) {
            return response()->json(['message' => 'Results are not yet published for your school'], 403);
        }

        $payload = $request->validate([
            'student' => 'nullable|string',
            'email' => 'nullable|string',
            'academic_session_id' => 'required|integer',
            'term_id' => 'required|integer',
        ]);

        $identifier = trim((string) ($payload['student'] ?? $payload['email'] ?? ''));
        if ($identifier === '') {
            return response()->json(['message' => 'Provide student email or name'], 422);
        }

        $schoolId = (int) $actor->school_id;
        $resolved = $this->resolveStudentByIdentifier($schoolId, $identifier);
        if (!$resolved) {
            return response()->json(['message' => 'Student not found for the supplied search value'], 404);
        }
        [$studentUser, $student] = $resolved;

        $session = AcademicSession::where('id', (int) $payload['academic_session_id'])
            ->where('school_id', $schoolId)
            ->first();
        if (!$session) {
            return response()->json(['message' => 'Academic session not found'], 422);
        }

        $term = Term::where('id', (int) $payload['term_id'])
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->first();
        if (!$term) {
            return response()->json(['message' => 'Term not found for selected session'], 404);
        }

        $classId = $this->resolveClassIdForTerm($schoolId, (int) $session->id, (int) $term->id, (int) $student->id);
        if (!$classId) {
            return response()->json(['message' => 'No class enrollment/result found for the selected student, session and term'], 404);
        }

        $class = SchoolClass::where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->first();
        if (!$class) {
            $class = SchoolClass::where('id', $classId)
                ->where('school_id', $schoolId)
                ->first();
        }
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        return $this->downloadPdfForResolvedStudent(
            $actor,
            $studentUser,
            $student,
            $session,
            $term,
            $class,
            $classId,
            (int) $term->id
        );
    }


    public function generateSchoolAdminStudentResultPdfDocumentForJob(
        int $requestingUserId,
        int $schoolId,
        string $identifier,
        int $academicSessionId,
        int $termId
    ): array {
        $actor = User::where('id', $requestingUserId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$actor || $actor->role !== 'school_admin') {
            throw new \RuntimeException('School admin user not found for result generation.');
        }

        $school = $actor->school ?: $actor->school()->first();
        if (!$school || !$school->results_published) {
            throw new \RuntimeException('Results are not yet published for your school.');
        }

        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new \RuntimeException('Provide student email or name.');
        }

        $resolved = $this->resolveStudentByIdentifier($schoolId, $identifier);
        if (!$resolved) {
            throw new \RuntimeException('Student not found for the supplied search value.');
        }
        [$studentUser, $student] = $resolved;

        $session = AcademicSession::where('id', $academicSessionId)
            ->where('school_id', $schoolId)
            ->first();
        if (!$session) {
            throw new \RuntimeException('Academic session not found.');
        }

        $term = Term::where('id', $termId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->first();
        if (!$term) {
            throw new \RuntimeException('Term not found for selected session.');
        }

        $classId = $this->resolveClassIdForTerm($schoolId, (int) $session->id, (int) $term->id, (int) $student->id);
        if (!$classId) {
            throw new \RuntimeException('No class enrollment/result found for the selected student, session and term.');
        }

        $class = SchoolClass::where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->first();
        if (!$class) {
            $class = SchoolClass::where('id', $classId)
                ->where('school_id', $schoolId)
                ->first();
        }
        if (!$class) {
            throw new \RuntimeException('Class not found.');
        }

        return $this->buildStudentResultPdfForResolvedStudent(
            $actor,
            $studentUser,
            $student,
            $session,
            $term,
            $class,
            (int) $class->id,
            (int) $term->id
        );
    }
    // GET /api/school-admin/reports/student-result?student=...&academic_session_id=...&term_id=...
    public function showForSchoolAdmin(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->role === 'school_admin', 403);

        if (!$actor->school || !$actor->school->results_published) {
            return response()->json(['message' => 'Results are not yet published for your school'], 403);
        }

        $payload = $request->validate([
            'student' => 'nullable|string',
            'email' => 'nullable|string',
            'academic_session_id' => 'required|integer',
            'term_id' => 'required|integer',
        ]);

        $identifier = trim((string) ($payload['student'] ?? $payload['email'] ?? ''));
        if ($identifier === '') {
            return response()->json(['message' => 'Provide student email or name'], 422);
        }

        $schoolId = (int) $actor->school_id;
        $resolved = $this->resolveStudentByIdentifier($schoolId, $identifier);
        if (!$resolved) {
            return response()->json(['message' => 'Student not found for the supplied search value'], 404);
        }
        [$studentUser, $student] = $resolved;

        $session = AcademicSession::where('id', (int) $payload['academic_session_id'])
            ->where('school_id', $schoolId)
            ->first();
        if (!$session) {
            return response()->json(['message' => 'Academic session not found'], 422);
        }

        $term = Term::where('id', (int) $payload['term_id'])
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->first();
        if (!$term) {
            return response()->json(['message' => 'Term not found for selected session'], 404);
        }

        $classId = $this->resolveClassIdForTerm($schoolId, (int) $session->id, (int) $term->id, (int) $student->id);
        if (!$classId) {
            return response()->json([
                'data' => [],
                'context' => [
                    'student' => [
                        'id' => (int) $studentUser->id,
                        'name' => $studentUser->name,
                        'email' => $studentUser->email,
                        'username' => $studentUser->username,
                    ],
                    'selected_session' => [
                        'id' => (int) $session->id,
                        'session_name' => $session->session_name,
                        'academic_year' => $session->academic_year,
                    ],
                    'selected_term' => [
                        'id' => (int) $term->id,
                        'name' => $term->name,
                    ],
                ],
                'message' => 'No class enrollment/result found for the selected student, session and term.',
            ]);
        }

        $class = SchoolClass::where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->first();
        if (!$class) {
            $class = SchoolClass::where('id', $classId)
                ->where('school_id', $schoolId)
                ->first();
        }
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $rows = $this->subjectRows($schoolId, (int) $class->id, (int) $term->id, (int) $student->id);
        $assessmentSchema = $this->assessmentSchemaForSchool($schoolId);
        $summary = $this->summarizeRows($schoolId, $rows);
        $totalScore = $summary['total_score'];
        $averageScore = $summary['average_score'];
        $overallGrade = $summary['overall_grade'];

        $behaviour = StudentBehaviourRating::where('school_id', $schoolId)
            ->where('class_id', (int) $class->id)
            ->where('term_id', (int) $term->id)
            ->where('student_id', (int) $student->id)
            ->first();

        $attendance = StudentAttendance::where('school_id', $schoolId)
            ->where('class_id', (int) $class->id)
            ->where('term_id', (int) $term->id)
            ->where('student_id', (int) $student->id)
            ->first();

        $teacherComment = (string) ($attendance?->comment ?? '');
        if ($teacherComment === '') {
            $teacherComment = $overallGrade !== '-'
                ? $this->defaultTeacherComment((int) round($averageScore))
                : 'No graded subject available yet.';
        }

        $behaviourTraits = [
            ['label' => 'Handwriting', 'value' => (int) ($behaviour?->handwriting ?? 0)],
            ['label' => 'Speech', 'value' => (int) ($behaviour?->speech ?? 0)],
            ['label' => 'Attitude', 'value' => (int) ($behaviour?->attitude ?? 0)],
            ['label' => 'Reading', 'value' => (int) ($behaviour?->reading ?? 0)],
            ['label' => 'Punctuality', 'value' => (int) ($behaviour?->punctuality ?? 0)],
            ['label' => 'Teamwork', 'value' => (int) ($behaviour?->teamwork ?? 0)],
            ['label' => 'Self Control', 'value' => (int) ($behaviour?->self_control ?? 0)],
        ];

        $behaviourSummary = collect($behaviourTraits)
            ->map(function (array $trait) {
                $label = match ((string) ($trait['label'] ?? '')) {
                    'Handwriting' => 'HW',
                    'Speech' => 'SP',
                    'Attitude' => 'AT',
                    'Reading' => 'RD',
                    'Punctuality' => 'PU',
                    'Teamwork' => 'TW',
                    'Self Control' => 'SC',
                    default => strtoupper(substr((string) ($trait['label'] ?? ''), 0, 2)),
                };

                return $label . ':' . (int) ($trait['value'] ?? 0);
            })
            ->implode(', ');

        return response()->json([
            'data' => [[
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
                    'graded_subjects_count' => $summary['graded_subjects_count'],
                    'total_score' => $totalScore,
                    'average_score' => $summary['graded_subjects_count'] > 0 ? $averageScore : null,
                    'overall_grade' => $overallGrade,
                ],
                'teacher_comment' => $teacherComment,
                'behaviour_summary' => $behaviourSummary,
                'behaviour_traits' => $behaviourTraits,
                'rows' => $rows,
                'assessment_schema' => $assessmentSchema,
            ]],
            'context' => [
                'student' => [
                    'id' => (int) $studentUser->id,
                    'name' => $studentUser->name,
                    'email' => $studentUser->email,
                    'username' => $studentUser->username,
                ],
                'selected_session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'selected_term' => [
                    'id' => (int) $term->id,
                    'name' => $term->name,
                ],
            ],
            'assessment_schema' => $assessmentSchema,
            'message' => count($rows) === 0 ? 'No result records found for the selected criteria.' : null,
        ]);
    }

    private function resolveStudentByIdentifier(int $schoolId, string $identifier): ?array
    {
        $identifier = strtolower(trim($identifier));
        if ($identifier === '') {
            return null;
        }

        $query = User::query()
            ->where('school_id', $schoolId)
            ->where('role', 'student');

        if (str_contains($identifier, '@')) {
            $query->whereRaw('LOWER(email) = ?', [$identifier]);
        } else {
            $like = '%' . $identifier . '%';
            $query->where(function ($q) use ($identifier, $like) {
                $q->whereRaw('LOWER(name) = ?', [$identifier])
                    ->orWhereRaw('LOWER(username) = ?', [$identifier])
                    ->orWhereRaw('LOWER(email) = ?', [$identifier])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            });
            $query->orderByRaw(
                'CASE WHEN LOWER(name)=? THEN 0 WHEN LOWER(username)=? THEN 1 WHEN LOWER(email)=? THEN 2 ELSE 3 END',
                [$identifier, $identifier, $identifier]
            );
        }

        $studentUser = $query
            ->orderBy('name')
            ->first(['id', 'name', 'email', 'username', 'photo_path', 'school_id']);

        if (!$studentUser) {
            return null;
        }

        $student = Student::where('school_id', $schoolId)
            ->where('user_id', (int) $studentUser->id)
            ->first();

        if (!$student) {
            return null;
        }

        return [$studentUser, $student];
    }

    private function resolveClassIdForTerm(int $schoolId, int $sessionId, int $termId, int $studentId): ?int
    {
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
    private function buildStudentResultPdfForResolvedStudent(
        User $actor,
        User $studentUser,
        Student $student,
        AcademicSession $session,
        Term $term,
        SchoolClass $class,
        int $classId,
        int $termId
    ): array {
        $schoolId = (int) $actor->school_id;

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $rows = $this->subjectRows($schoolId, $classId, $termId, (int) $student->id);

        $behaviour = StudentBehaviourRating::where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->where('student_id', $student->id)
            ->first();

        $attendance = StudentAttendance::where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->where('student_id', $student->id)
            ->first();
        $attendanceSetting = TermAttendanceSetting::where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->first();

        $classTeacher = $this->resolveClassTeacherForStudentTerm(
            $schoolId,
            $class,
            (int) $student->id,
            $termId
        );

        $school = $actor->school ?: $actor->school()->first();
        $studentPhotoPath = $student?->photo_path ?: $studentUser?->photo_path;

        $assessmentSchema = $this->assessmentSchemaForSchool($schoolId);
        $summary = $this->summarizeRows($schoolId, $rows);
        $totalScore = $summary['total_score'];
        $averageScore = $summary['average_score'];
        $overallGrade = $summary['overall_grade'];
        $averageDisplay = $summary['average_display'];

        $teacherComment = (string) ($attendance?->comment ?? '');
        if ($teacherComment === '') {
            $teacherComment = $overallGrade !== '-'
                ? $this->defaultTeacherComment((int) round($averageScore))
                : 'No graded subject available yet.';
        }
        $schoolHeadComment = $overallGrade !== '-'
            ? $this->defaultHeadComment((int) round($averageScore))
            : 'No graded subject available yet.';

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
            'averageDisplay' => $averageDisplay,
            'overallGrade' => $overallGrade,
            'attendance' => $attendance,
            'attendanceSetting' => $attendanceSetting,
            'nextTermBeginDate' => $attendanceSetting?->next_term_begin_date,
            'teacherComment' => $teacherComment,
            'schoolHeadComment' => $schoolHeadComment,
            'classTeacher' => $classTeacher,
            'behaviourTraits' => $behaviourTraits,
            'assessmentSchema' => $assessmentSchema,
            'schoolLogoDataUri' => $this->toDataUri($school?->logo_path),
            'studentPhotoDataUri' => $this->studentPhotoDataUri($studentPhotoPath),
            'headSignatureDataUri' => $this->toDataUri($school?->head_signature_path),
        ];

        $viewDataWithAssets = $viewData;
        $viewDataWithoutAssets = $viewData;
        $viewDataWithoutAssets['schoolLogoDataUri'] = null;
        $viewDataWithoutAssets['studentPhotoDataUri'] = null;
        $viewDataWithoutAssets['headSignatureDataUri'] = null;

        try {
            $pdfOutput = $this->renderStudentResultPdf($viewDataWithAssets);
        } catch (Throwable $pdfError) {
            Log::warning('Student result PDF primary render failed, retrying without embedded images', [
                'school_id' => $schoolId,
                'actor_user_id' => $actor->id ?? null,
                'student_user_id' => $studentUser->id ?? null,
                'student_id' => $student->id ?? null,
                'class_id' => $classId,
                'term_id' => $termId,
                'error' => $pdfError->getMessage(),
            ]);

            try {
                $pdfOutput = $this->renderStudentResultPdf($viewDataWithoutAssets);
            } catch (Throwable $fallbackError) {
                Log::warning('Student result PDF image-free render failed, trying simplified template with assets', [
                    'school_id' => $schoolId,
                    'actor_user_id' => $actor->id ?? null,
                    'student_user_id' => $studentUser->id ?? null,
                    'student_id' => $student->id ?? null,
                    'class_id' => $classId,
                    'term_id' => $termId,
                    'error' => $fallbackError->getMessage(),
                ]);

                try {
                    $pdfOutput = $this->renderSimpleStudentResultPdf($viewDataWithAssets);
                } catch (Throwable $simpleWithAssetError) {
                    Log::warning('Student result simplified template with assets failed, using simplified template without assets', [
                        'school_id' => $schoolId,
                        'actor_user_id' => $actor->id ?? null,
                        'student_user_id' => $studentUser->id ?? null,
                        'student_id' => $student->id ?? null,
                        'class_id' => $classId,
                        'term_id' => $termId,
                        'error' => $simpleWithAssetError->getMessage(),
                    ]);

                    $pdfOutput = $this->renderSimpleStudentResultPdf($viewDataWithoutAssets);
                }
            }
        }

        $safeStudent = Str::slug((string) ($studentUser->name ?: 'student'));
        $safeTerm = Str::slug((string) ($term->name ?: 'term'));
        $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));

        return [
            'pdf_output' => $pdfOutput,
            'file_name' => "{$safeStudent}_{$safeSession}_{$safeTerm}_result.pdf",
        ];
    }

    private function downloadPdfForResolvedStudent(
        User $actor,
        User $studentUser,
        Student $student,
        AcademicSession $session,
        Term $term,
        SchoolClass $class,
        int $classId,
        int $termId
    ) {
        $schoolId = (int) $actor->school_id;

        try {
            $generated = $this->buildStudentResultPdfForResolvedStudent(
                $actor,
                $studentUser,
                $student,
                $session,
                $term,
                $class,
                $classId,
                $termId
            );

            return response($generated['pdf_output'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $generated['file_name'] . '"',
            ]);
        } catch (Throwable $e) {
            Log::error('School-admin student result PDF generation failed', [
                'school_id' => $schoolId,
                'actor_user_id' => $actor->id ?? null,
                'student_user_id' => $studentUser->id ?? null,
                'student_id' => $student->id ?? null,
                'class_id' => $classId,
                'term_id' => $termId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to generate result PDF. Please check student/session data and branding images.',
            ], 500);
        }
    }

    private function subjectRows(int $schoolId, int $classId, int $termId, int $studentId): array
    {
        $assessmentSchema = $this->assessmentSchemaForSchool($schoolId);
        $termSessionId = (int) (Term::query()->where('id', $termId)->value('academic_session_id') ?? 0);

        $subjects = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.class_id', $classId)
            ->where('term_subjects.term_id', $termId)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->when(
                $termSessionId > 0 && Schema::hasTable('student_subject_exclusions'),
                function ($query) use ($schoolId, $classId, $studentId, $termSessionId) {
                    $query->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $classId, $studentId, $termSessionId) {
                        $join->on('student_subject_exclusions.class_id', '=', 'term_subjects.class_id')
                            ->on('student_subject_exclusions.subject_id', '=', 'term_subjects.subject_id')
                            ->where('student_subject_exclusions.school_id', '=', $schoolId)
                            ->where('student_subject_exclusions.academic_session_id', '=', $termSessionId)
                            ->where('student_subject_exclusions.class_id', '=', $classId)
                            ->where('student_subject_exclusions.student_id', '=', $studentId);
                    })
                    ->whereNull('student_subject_exclusions.id');
                }
            )
            ->leftJoin('results', function ($join) use ($studentId) {
                $join->on('results.term_subject_id', '=', 'term_subjects.id')
                    ->where('results.student_id', '=', $studentId);
            })
            ->select([
                'term_subjects.id as term_subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
                'results.id as result_id',
                'results.student_id as result_student_id',
                'results.ca',
                'results.ca_breakdown',
                'results.exam',
                'results.created_at as result_created_at',
                'results.updated_at as result_updated_at',
            ])
            ->orderBy('subjects.name')
            ->get();

        $termSubjectIds = $subjects->pluck('term_subject_id')->map(fn ($id) => (int) $id)->all();
        $subjectStats = $this->buildSubjectStats($schoolId, $termSubjectIds);

        return $subjects
            ->map(function ($r) use ($subjectStats, $studentId, $assessmentSchema, $schoolId) {
                $isGraded = $this->isResultRecordGraded(
                    $r->result_id ?? null,
                    $r->ca ?? null,
                    $r->exam ?? null,
                    $r->result_created_at ?? null,
                    $r->result_updated_at ?? null
                );
                $termSubjectId = (int) $r->term_subject_id;
                $stats = $subjectStats[$termSubjectId] ?? null;
                $position = $isGraded ? ($stats['positions'][$studentId] ?? null) : null;

                if ($isGraded) {
                    $caBreakdown = AssessmentSchema::normalizeBreakdown(
                        $r->ca_breakdown ?? null,
                        $assessmentSchema,
                        (int) ($r->ca ?? 0)
                    );
                    $ca = AssessmentSchema::breakdownTotal($caBreakdown);
                    $exam = max(0, min((int) $assessmentSchema['exam_max'], (int) ($r->exam ?? 0)));
                    $total = $ca + $exam;
                    $grade = $this->gradeFromTotal($schoolId, $total);
                    $remark = $this->remarkFromTotal($schoolId, $total);
                    $caBreakdownText = AssessmentSchema::formatBreakdown($caBreakdown, $assessmentSchema);
                } else {
                    $caBreakdown = array_fill(0, 5, null);
                    $ca = '-';
                    $exam = '-';
                    $total = '-';
                    $grade = '-';
                    $remark = '-';
                    $caBreakdownText = '-';
                }

                return [
                    'term_subject_id' => $termSubjectId,
                    'subject_name' => $r->subject_name,
                    'subject_code' => $r->subject_code,
                    'is_graded' => $isGraded,
                    'ca' => $ca,
                    'ca_breakdown' => $caBreakdown,
                    'ca_breakdown_text' => $caBreakdownText,
                    'exam' => $exam,
                    'total' => $total,
                    'min_score' => $isGraded ? ($stats['min_score'] ?? 0) : '-',
                    'max_score' => $isGraded ? ($stats['max_score'] ?? 0) : '-',
                    'class_average' => $isGraded ? ($stats['class_average'] ?? 0) : '-',
                    'position' => $position,
                    'position_label' => $position ? $this->ordinalPosition($position) : '-',
                    'grade' => $grade,
                    'remark' => $remark,
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

        $rowsQuery = DB::table('results')
            ->where('results.school_id', $schoolId)
            ->whereIn('results.term_subject_id', $termSubjectIds);

        if (Schema::hasTable('student_subject_exclusions')) {
            $rowsQuery
                ->join('term_subjects', 'term_subjects.id', '=', 'results.term_subject_id')
                ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
                ->leftJoin('student_subject_exclusions', function ($join) use ($schoolId) {
                    $join->on('student_subject_exclusions.class_id', '=', 'term_subjects.class_id')
                        ->on('student_subject_exclusions.subject_id', '=', 'term_subjects.subject_id')
                        ->on('student_subject_exclusions.student_id', '=', 'results.student_id')
                        ->on('student_subject_exclusions.academic_session_id', '=', 'terms.academic_session_id')
                        ->where('student_subject_exclusions.school_id', '=', $schoolId);
                })
                ->whereNull('student_subject_exclusions.id');
        }

        $rows = $rowsQuery
            ->select(['results.id', 'results.term_subject_id', 'results.student_id', 'results.ca', 'results.exam', 'results.created_at', 'results.updated_at'])
            ->get();

        $gradedRows = $rows->filter(function ($row) {
            return $this->isResultRecordGraded(
                $row->id ?? null,
                $row->ca ?? null,
                $row->exam ?? null,
                $row->created_at ?? null,
                $row->updated_at ?? null
            );
        });

        $grouped = $gradedRows->groupBy(fn ($r) => (int) $r->term_subject_id);
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

    private function summarizeRows(int $schoolId, array $rows): array
    {
        $gradedRows = collect($rows)
            ->filter(fn ($row) => (bool) ($row['is_graded'] ?? false))
            ->values();

        $gradedCount = $gradedRows->count();
        $totalScore = (int) $gradedRows->sum(fn ($row) => (int) ($row['total'] ?? 0));
        $averageScore = $gradedCount > 0
            ? (float) round($totalScore / $gradedCount, 2)
            : 0.0;

        return [
            'graded_subjects_count' => $gradedCount,
            'total_score' => $totalScore,
            'average_score' => $averageScore,
            'average_display' => $gradedCount > 0 ? number_format($averageScore, 2) : '-',
            'overall_grade' => $gradedCount > 0
                ? $this->gradeFromTotal($schoolId, (int) round($averageScore))
                : '-',
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

    private function assessmentSchemaForSchool(int $schoolId): array
    {
        static $cache = [];

        if (isset($cache[$schoolId])) {
            return $cache[$schoolId];
        }

        $schema = School::where('id', $schoolId)->value('assessment_schema');
        $cache[$schoolId] = AssessmentSchema::normalizeSchema($schema);

        return $cache[$schoolId];
    }

    private function gradingSchemaForSchool(int $schoolId): array
    {
        static $cache = [];

        if (isset($cache[$schoolId])) {
            return $cache[$schoolId];
        }

        $schema = School::where('id', $schoolId)->value('grading_schema');
        $cache[$schoolId] = GradingSchema::normalize($schema);

        return $cache[$schoolId];
    }

    private function gradeFromTotal(int $schoolId, int $total): string
    {
        return GradingSchema::gradeForTotal($this->gradingSchemaForSchool($schoolId), $total);
    }

    private function remarkFromTotal(int $schoolId, int $total): string
    {
        return GradingSchema::remarkForTotal($this->gradingSchemaForSchool($schoolId), $total);
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

    private function defaultTeacherComment(int $score): string
    {
        return match (true) {
            $score >= 70 => 'Excellent performance. Keep maintaining this standard.',
            $score >= 60 => 'Very good result. Keep pushing for excellence.',
            $score >= 50 => 'Good effort. More consistency is needed.',
            $score >= 40 => 'Fair performance. Needs more attention and practice.',
            $score >= 30 => 'Below average performance. Improvement is required.',
            default => 'Poor performance. Immediate intervention is advised.',
        };
    }

    private function defaultHeadComment(int $score): string
    {
        return match (true) {
            $score >= 70 => 'Impressive performance. Keep aiming higher and stay focused.',
            $score >= 60 => 'Very good result. With more effort, you can reach excellent level.',
            $score >= 50 => 'Good progress. Stay consistent and improve in weaker subjects.',
            $score >= 40 => 'You can do better. More reading and guidance are needed.',
            $score >= 30 => 'Significant improvement is required. Parents and teachers should monitor closely.',
            default => 'Performance is below expectation. Immediate academic support is required.',
        };
    }

    private function studentPhotoDataUri(?string $storagePath): ?string
    {
        return $this->toDataUri($storagePath)
            ?: $this->localImageToDataUri(base_path('public/defaults/student-photo-placeholder.svg'));
    }

    private function toDataUri(?string $storagePath): ?string
    {
        try {
            if (!$storagePath) {
                return null;
            }

            $fullPath = Storage::disk('public')->path($storagePath);
            return $this->localImageToDataUri($fullPath);
        } catch (Throwable $e) {
            Log::warning('Failed to build image data URI for student result PDF', [
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function localImageToDataUri(string $fullPath): ?string
    {
        if (!is_file($fullPath)) {
            return null;
        }

        $size = @filesize($fullPath);
        if (is_int($size) && $size > 700 * 1024) {
            return null;
        }

        $mime = strtolower((string) (mime_content_type($fullPath) ?: ''));
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/svg+xml', 'text/plain'];
        if (!in_array($mime, $allowedMimes, true)) {
            return null;
        }

        $binary = @file_get_contents($fullPath);
        if (!is_string($binary) || $binary === '') {
            return null;
        }

        $mime = str_ends_with(strtolower($fullPath), '.svg') ? 'image/svg+xml' : $mime;
        $base64 = base64_encode($binary);

        return "data:{$mime};base64,{$base64}";
    }

    private function renderStudentResultPdf(array $viewData): string
    {
        $html = view('pdf.student_result', $viewData)->render();
        return $this->renderPdfFromHtml($html);
    }

    private function renderSimpleStudentResultPdf(array $viewData): string
    {
        $schoolName = strtoupper((string) data_get($viewData, 'school.name', 'SCHOOL'));
        $schoolLocation = strtoupper((string) data_get($viewData, 'school.location', ''));
        $studentName = strtoupper((string) data_get($viewData, 'studentUser.name', '-'));
        $studentSerial = strtoupper((string) data_get($viewData, 'studentUser.username', '-'));
        $studentSexRaw = trim((string) data_get($viewData, 'student.sex', ''));
        $studentSex = $studentSexRaw !== '' ? strtoupper($studentSexRaw) : '-';
        $className = strtoupper((string) data_get($viewData, 'class.name', '-'));
        $termName = strtoupper((string) data_get($viewData, 'term.name', '-'));
        $sessionName = strtoupper((string) (data_get($viewData, 'session.academic_year') ?: data_get($viewData, 'session.session_name', '-')));
        $teacherComment = strtoupper((string) data_get($viewData, 'teacherComment', '-'));
        $headComment = strtoupper((string) data_get($viewData, 'schoolHeadComment', '-'));
        $classTeacherName = strtoupper((string) data_get($viewData, 'classTeacher.name', '-'));
        $average = (string) data_get(
            $viewData,
            'averageDisplay',
            number_format((float) data_get($viewData, 'averageScore', 0), 2)
        );
        $total = (int) data_get($viewData, 'totalScore', 0);
        $schoolLogoDataUri = (string) data_get($viewData, 'schoolLogoDataUri', '');
        $studentPhotoDataUri = (string) data_get($viewData, 'studentPhotoDataUri', '');
        $headSignatureDataUri = (string) data_get($viewData, 'headSignatureDataUri', '');
        $behaviourTraits = (array) data_get($viewData, 'behaviourTraits', []);
        $headName = strtoupper((string) data_get($viewData, 'school.head_of_school_name', '-'));
        $timesPresent = (int) data_get($viewData, 'attendance.days_present', 0);
        $timesSchoolOpened = (int) data_get($viewData, 'attendanceSetting.total_school_days', 0);
        $attendanceSummary = $timesSchoolOpened > 0
            ? ($timesPresent . '/' . $timesSchoolOpened)
            : (string) $timesPresent;
        $nextTermBeginLabel = '-';
        $nextTermBeginDate = data_get($viewData, 'nextTermBeginDate');
        if (!empty($nextTermBeginDate)) {
            try {
                $nextTermBeginLabel = \Carbon\Carbon::parse($nextTermBeginDate)->format('jS M, Y');
            } catch (Throwable $e) {
                $nextTermBeginLabel = '-';
            }
        }
        $assessmentSchema = AssessmentSchema::normalizeSchema(data_get($viewData, 'assessmentSchema', []));
        $caSummaryParts = [];
        $activeCaIndices = AssessmentSchema::activeCaIndices($assessmentSchema);
        foreach ($activeCaIndices as $index) {
            $caSummaryParts[] = 'CA' . ($index + 1) . ' (' . ((int) ($assessmentSchema['ca_maxes'][$index] ?? 0)) . ')';
        }
        $assessmentSummary = implode(', ', $caSummaryParts) . ' | EXAM (' . ((int) $assessmentSchema['exam_max']) . ')';

        $caHeaderHtml = '';
        foreach ($activeCaIndices as $index) {
            $caHeaderHtml .= '<th style="width:8%;">C' . ($index + 1) . ' (' . ((int) ($assessmentSchema['ca_maxes'][$index] ?? 0)) . ')</th>';
        }

        $rowsHtml = '';
        foreach ((array) data_get($viewData, 'rows', []) as $row) {
            $subject = strtoupper((string) ($row['subject_name'] ?? '-'));
            $exam = $row['exam'] ?? '-';
            $score = $row['total'] ?? '-';
            $grade = strtoupper((string) ($row['grade'] ?? '-'));
            $remark = strtoupper((string) ($row['remark'] ?? '-'));
            $caCellsHtml = '';
            foreach ($activeCaIndices as $index) {
                $caValue = $row['ca_breakdown'][$index] ?? null;
                $caCellsHtml .= '<td style="text-align:center;">'
                    . (($caValue === null || $caValue === '') ? '-' : (int) $caValue)
                    . '</td>';
            }

            $rowsHtml .= '<tr>'
                . '<td>' . e($subject) . '</td>'
                . $caCellsHtml
                . '<td style="text-align:center;">' . $exam . '</td>'
                . '<td style="text-align:center;">' . $score . '</td>'
                . '<td style="text-align:center;">' . e($grade) . '</td>'
                . '<td>' . e($remark) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="' . (5 + count($activeCaIndices)) . '" style="text-align:center;">No result data found.</td></tr>';
        }

        $behaviourCellsPerRow = 5;
        $behaviourHeaderHtml = '';
        for ($i = 0; $i < $behaviourCellsPerRow; $i++) {
            $behaviourHeaderHtml .= '<th style="width:16%;">PSYCHOMOTOR</th><th style="width:4%;text-align:center;">RATE</th>';
        }
        $behaviourRowsHtml = '';
        $behaviourChunks = collect($behaviourTraits)->values()->chunk($behaviourCellsPerRow);
        if ($behaviourChunks->isEmpty()) {
            $behaviourChunks = collect([collect()]);
        }
        foreach ($behaviourChunks as $chunk) {
            $behaviourRowsHtml .= '<tr>';
            for ($i = 0; $i < $behaviourCellsPerRow; $i++) {
                $trait = $chunk->get($i);
                $label = strtoupper((string) ($trait['label'] ?? ''));
                $value = $trait ? (string) ((int) ($trait['value'] ?? 0)) : '';
                $behaviourRowsHtml .= '<td>' . e($label) . '</td><td style="text-align:center;">' . e($value) . '</td>';
            }
            $behaviourRowsHtml .= '</tr>';
        }

        $studentPhotoBlock = $studentPhotoDataUri !== ''
            ? '<img src="' . e($studentPhotoDataUri) . '" alt="Student Photo" style="width:72px;height:72px;object-fit:cover;border:1px solid #222;" />'
            : '<div style="width:72px;height:72px;border:1px solid #222;"></div>';
        $schoolLogoBlock = $schoolLogoDataUri !== ''
            ? '<img src="' . e($schoolLogoDataUri) . '" alt="School Logo" style="width:72px;height:72px;object-fit:contain;border:1px solid #222;" />'
            : '<div style="width:72px;height:72px;border:1px solid #222;"></div>';

        $signatureBlock = $headSignatureDataUri !== ''
            ? '<img src="' . e($headSignatureDataUri) . '" alt="Head Signature" style="width:140px;height:48px;object-fit:contain;border-bottom:1px dashed #6b7280;" />'
            : '<div style="width:140px;height:48px;border-bottom:1px dashed #6b7280;"></div>';
        $watermarkBlock = $schoolLogoDataUri !== ''
            ? '<img class="wm" src="' . e($schoolLogoDataUri) . '" alt="" />'
            : '';

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Student Result</title>'
            . '<style>'
            . 'body{font-family:Arial,Helvetica,DejaVu Sans,sans-serif;font-size:9px;color:#111;}'
            . '.sheet{position:relative;border:1px solid #d1d5db;padding:10px;overflow:hidden;}'
            . '.wm{position:absolute;top:28%;left:50%;width:300px;height:300px;margin-left:-150px;opacity:.07;object-fit:contain;z-index:0;}'
            . '.content{position:relative;z-index:1;}'
            . 'h1{margin:0;font-size:16px;text-align:center;}'
            . 'h2{margin:3px 0 8px 0;font-size:11px;text-align:center;font-weight:600;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:8px;}'
            . 'th,td{border:1px solid #222;padding:4px;}'
            . 'th{background:#f3f4f6;text-align:left;}'
            . '.meta td,.meta th{font-size:9px;}'
            . '.grid{width:100%;margin-top:8px;}'
            . '.grid td{vertical-align:top;border:0;padding:0;}'
            . '.section-title{font-weight:bold;margin-top:8px;}'
            . '.footer-container{margin-top:8px;border:1px solid #222;padding:6px;}'
            . '.info-box{width:100%;border-collapse:collapse;margin-top:4px;}'
            . '.info-box:first-child{margin-top:0;}'
            . '.info-box th,.info-box td{border:1px solid #222;padding:5px 6px;}'
            . '.info-box th{background:#f3f4f6;text-align:left;width:28%;}'
            . '.signature-only{border:1px solid #222;min-height:64px;display:flex;align-items:center;justify-content:center;padding:4px;text-align:center;}'
            . '</style></head><body><div class="sheet">' . $watermarkBlock . '<div class="content">'
            . '<table class="grid"><tr><td style="width:80px;">' . $studentPhotoBlock . '</td><td>'
            . '<h1>' . e($schoolName) . '</h1>'
            . '<h2>' . e($schoolLocation) . '</h2>'
            . '<h2>RESULT SHEET FOR ' . e($termName) . ' - ' . e($sessionName) . '</h2>'
            . '</td><td style="width:80px;text-align:right;">' . $schoolLogoBlock . '</td></tr></table>'
            . '<table class="meta">'
            . '<tr><th style="width:20%;">Student</th><td style="width:30%;">' . e($studentName) . '</td><th style="width:20%;">Serial No</th><td style="width:30%;">' . e($studentSerial) . '</td></tr>'
            . '<tr><th>Class</th><td>' . e($className) . '</td><th>Average</th><td>' . e($average) . '</td></tr>'
            . '<tr><th>Gender</th><td>' . e($studentSex) . '</td><th>Next Term Begins</th><td>' . e($nextTermBeginLabel) . '</td></tr>'
            . '<tr><th>Attendance</th><td>' . e($attendanceSummary) . '</td><th>Total Number of School Open</th><td>' . $timesSchoolOpened . '</td></tr>'
            . '<tr><th>Total Score</th><td>' . $total . '</td><th>Term</th><td>' . e($termName) . '</td></tr>'
            . '<tr><th>Assessment Pattern</th><td colspan="3">' . e($assessmentSummary) . '</td></tr>'
            . '</table>'
            . '<table>'
            . '<thead><tr><th style="width:30%;">Subject</th>'
            . $caHeaderHtml
            . '<th style="width:8%;">Exam (' . ((int) $assessmentSchema['exam_max']) . ')</th><th style="width:8%;">Total</th><th style="width:8%;">Grade</th><th style="width:16%;">Remark</th></tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '<table class="meta"><tr><th style="width:18%;">GRADES</th>'
            . '<td style="width:82%;">' . e(GradingSchema::displayKey(data_get($viewData, 'school.grading_schema'))) . '</td></tr></table>'
            . '<table><thead><tr>' . $behaviourHeaderHtml . '</tr></thead><tbody>'
            . $behaviourRowsHtml
            . '</tbody></table>'
            . '<table class="meta" style="margin-top:0;"><tr><th style="width:18%;">KEY RATING</th>'
            . '<td style="width:82%;">5 - EXCELLENT | 4 - VERY GOOD | 3 - SATISFACTORY | 2 - POOR | 1 - VERY POOR</td></tr></table>'
            . '<div class="footer-container">'
            . '<table class="grid" style="margin-top:0;"><tr><td style="width:74%;">'
            . '<table class="info-box"><tr><th>Head of School Name</th><td>' . e($headName) . '</td></tr></table>'
            . '<table class="info-box"><tr><th>Head of School Comment</th><td>' . e($headComment) . '</td></tr></table>'
            . '<table class="info-box"><tr><th>Class Teacher Name</th><td>' . e($classTeacherName) . '</td></tr></table>'
            . '<table class="info-box"><tr><th>Class Teacher Comment</th><td>' . e($teacherComment) . '</td></tr></table>'
            . '</td><td style="width:2%;"></td><td style="width:16%;">'
            . '<div class="signature-only">' . $signatureBlock . '</div>'
            . '</td></tr></table>'
            . '</div>'
            . '</div></div></body></html>';

        return $this->renderPdfFromHtml($html);
    }

    private function renderPdfFromHtml(string $html): string
    {
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
}










