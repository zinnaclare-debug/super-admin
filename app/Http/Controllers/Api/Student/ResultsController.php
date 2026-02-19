<?php

namespace App\Http\Controllers\Api\Student;

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
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ResultsController extends Controller
{
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

        return response()->json(['data' => $rows]);
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

        $classTeacher = null;
        if ($class->class_teacher_user_id) {
            $classTeacher = \App\Models\User::where('id', $class->class_teacher_user_id)
                ->where('school_id', $schoolId)
                ->first(['id', 'name', 'email']);
        }

        $school = $user->school;

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

        $behaviourTraits = [
            ['label' => 'Handwriting', 'value' => (int) ($behaviour?->handwriting ?? 0)],
            ['label' => 'Speech', 'value' => (int) ($behaviour?->speech ?? 0)],
            ['label' => 'Attitude', 'value' => (int) ($behaviour?->attitude ?? 0)],
            ['label' => 'Reading', 'value' => (int) ($behaviour?->reading ?? 0)],
            ['label' => 'Punctuality', 'value' => (int) ($behaviour?->punctuality ?? 0)],
            ['label' => 'Teamwork', 'value' => (int) ($behaviour?->teamwork ?? 0)],
            ['label' => 'Self Control', 'value' => (int) ($behaviour?->self_control ?? 0)],
        ];

        $html = view('pdf.student_result', [
            'school' => $school,
            'session' => $session,
            'term' => $term,
            'class' => $class,
            'student' => $student,
            'studentUser' => $user,
            'rows' => $rows,
            'totalScore' => $totalScore,
            'averageScore' => $averageScore,
            'overallGrade' => $overallGrade,
            'attendance' => $attendance,
            'nextTermBeginDate' => $attendanceSetting?->next_term_begin_date,
            'teacherComment' => $teacherComment,
            'classTeacher' => $classTeacher,
            'behaviourTraits' => $behaviourTraits,
            'schoolLogoDataUri' => $this->toDataUri($school?->logo_path),
            'headSignatureDataUri' => $this->toDataUri($school?->head_signature_path),
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

        $safeStudent = Str::slug((string) $user->name ?: 'student');
        $safeTerm = Str::slug((string) $term->name ?: 'term');
        $safeSession = Str::slug((string) ($session->academic_year ?: $session->session_name ?: 'session'));
        $filename = "{$safeStudent}_{$safeSession}_{$safeTerm}_result.pdf";

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function subjectRows(int $schoolId, int $classId, int $termId, int $studentId): array
    {
        return TermSubject::query()
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
                'results.ca',
                'results.exam',
            ])
            ->orderBy('subjects.name')
            ->get()
            ->map(function ($r) {
                $ca = (int) ($r->ca ?? 0);
                $exam = (int) ($r->exam ?? 0);
                $total = $ca + $exam;

                return [
                    'term_subject_id' => (int) $r->term_subject_id,
                    'subject_name' => $r->subject_name,
                    'subject_code' => $r->subject_code,
                    'ca' => $ca,
                    'exam' => $exam,
                    'total' => $total,
                    'grade' => $this->gradeFromTotal($total),
                ];
            })
            ->values()
            ->all();
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

    private function toDataUri(?string $storagePath): ?string
    {
        if (!$storagePath) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($storagePath);
        if (!is_file($fullPath)) {
            return null;
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';
        $base64 = base64_encode((string) file_get_contents($fullPath));

        return "data:{$mime};base64,{$base64}";
    }
}
