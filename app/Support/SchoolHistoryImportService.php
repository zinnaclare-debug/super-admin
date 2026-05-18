<?php

namespace App\Support;

use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermSubject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class SchoolHistoryImportService
{
    private array $warnings = [];
    private array $studentsByKey = [];
    private array $pendingPasswordsByUserId = [];
    private array $latestPlacementByStudentId = [];
    private array $sessionOrder = [];

    private array $summary = [
        'rows_read' => 0,
        'rows_imported' => 0,
        'rows_skipped' => 0,
        'sessions_created' => 0,
        'terms_created' => 0,
        'classes_created' => 0,
        'departments_created' => 0,
        'subjects_created' => 0,
        'students_created' => 0,
        'students_updated' => 0,
        'active_students' => 0,
        'inactive_students' => 0,
        'graduated_students' => 0,
        'logins_created' => 0,
        'results_saved' => 0,
    ];

    private const BASE_KEYS = [
        'academic_session',
        'session',
        'session_name',
        'term',
        'term_name',
        'education_level',
        'level',
        'class',
        'class_name',
        'department',
        'sub_class',
        'student_name',
        'name',
        'full_name',
        'first_name',
        'last_name',
        'admission_no',
        'admission_number',
        'student_id',
        'id_number',
        'username',
        'email',
        'student_email',
        'gender',
        'sex',
        'status',
        'subject',
        'subject_name',
        'ca',
        'exam',
        'score',
        'total',
        'total_score',
        'grade',
    ];

    public function import(School $school, UploadedFile $file, int $actorUserId, bool $makeLatestSessionCurrent = false): array
    {
        $this->reset();

        $rows = $this->readCsv($file);
        if (empty($rows)) {
            throw new RuntimeException('The uploaded CSV is empty.');
        }

        DB::transaction(function () use ($school, $rows, $actorUserId, $makeLatestSessionCurrent) {
            foreach ($rows as $index => $row) {
                $this->summary['rows_read']++;
                $this->importRow($school, $row, $index + 2, $actorUserId);
            }

            $this->finalizeStudentStatuses($school, $actorUserId);

            if ($makeLatestSessionCurrent && !empty($this->sessionOrder)) {
                $latestSessionId = (int) array_key_last($this->sessionOrder);
                AcademicSession::query()
                    ->where('school_id', (int) $school->id)
                    ->where('id', '!=', $latestSessionId)
                    ->update(['status' => 'completed']);
                AcademicSession::query()
                    ->where('school_id', (int) $school->id)
                    ->where('id', $latestSessionId)
                    ->update(['status' => 'current']);
            }
        });

        return [
            'summary' => $this->summary,
            'warnings' => array_slice($this->warnings, 0, 40),
        ];
    }

    public static function templateCsv(): string
    {
        $headers = [
            'session',
            'term',
            'level',
            'class',
            'department',
            'student_name',
            'admission_no',
            'email',
            'gender',
            'status',
            'subject',
            'ca',
            'exam',
            'score',
        ];

        $rows = [
            $headers,
            ['2024/2025', 'First Term', 'secondary', 'SS 3', 'Science', 'Ada Example', 'ADM001', 'ada@example.com', 'F', 'graduated', 'Mathematics', '28', '62', ''],
            ['2024/2025', 'First Term', 'secondary', 'SS 2', 'Science', 'Tunde Example', 'ADM002', 'tunde@example.com', 'M', 'active', 'English Language', '25', '58', ''],
        ];

        return collect($rows)
            ->map(fn (array $row) => collect($row)->map(fn ($value) => self::csvCell((string) $value))->implode(','))
            ->implode("\n") . "\n";
    }

    private static function csvCell(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function reset(): void
    {
        $this->warnings = [];
        $this->studentsByKey = [];
        $this->pendingPasswordsByUserId = [];
        $this->latestPlacementByStudentId = [];
        $this->sessionOrder = [];
    }

    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to read the uploaded CSV.');
        }

        $headers = null;
        $rows = [];

        while (($raw = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($raw);
                continue;
            }

            if ($this->rowIsBlank($raw)) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = [
                    'label' => (string) ($raw[$index] ?? ''),
                    'value' => trim((string) ($raw[$index] ?? '')),
                ];
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $clean = strtolower(trim((string) $header));
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean) ?? $clean;
            $clean = preg_replace('/[^a-z0-9]+/', '_', $clean) ?? $clean;
            return trim($clean, '_');
        }, $headers);
    }

    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function importRow(School $school, array $row, int $lineNumber, int $actorUserId): void
    {
        $sessionName = $this->value($row, ['session', 'academic_session', 'session_name']);
        $termName = $this->value($row, ['term', 'term_name']) ?: 'First Term';
        $className = $this->value($row, ['class', 'class_name']);
        $studentName = $this->studentName($row);

        if ($sessionName === '' || $className === '' || $studentName === '') {
            $this->skip($lineNumber, 'Session, class, and student name are required.');
            return;
        }

        $level = $this->normalizeLevel($this->value($row, ['level', 'education_level']), $className);
        $departmentName = $this->value($row, ['department', 'sub_class']);
        $declaredStatus = $this->normalizeStatus($this->value($row, ['status']));

        $session = $this->session($school, $sessionName);
        $term = $this->term($school, $session, $termName);
        $class = $this->schoolClass($school, $session, $level, $className);
        $department = $departmentName !== '' ? $this->department($school, $class, $departmentName) : null;
        $student = $this->student($school, $row, $studentName, $level, $declaredStatus, $actorUserId);

        $this->enroll($school, $session, $term, $class, $student, $department);
        $this->rememberLatestPlacement($student, $session, $class, $declaredStatus);

        $subjectRows = $this->subjectRows($row);
        if (empty($subjectRows)) {
            $this->summary['rows_imported']++;
            return;
        }

        foreach ($subjectRows as $subjectRow) {
            $subjectName = trim((string) ($subjectRow['subject'] ?? ''));
            if ($subjectName === '') {
                continue;
            }

            $scores = $this->scores($subjectRow);
            if ($scores === null) {
                $this->warn($lineNumber, "Skipped {$subjectName}: score is not numeric.");
                continue;
            }

            $subject = $this->subject($school, $subjectName);
            $termSubject = $this->termSubject($term, $class, $subject, (int) $school->id);

            Result::query()->updateOrCreate([
                'term_subject_id' => (int) $termSubject->id,
                'student_id' => (int) $student->id,
            ], [
                'school_id' => (int) $school->id,
                'ca' => $scores['ca'],
                'exam' => $scores['exam'],
            ]);

            $this->summary['results_saved']++;
        }

        $this->summary['rows_imported']++;
    }

    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key])) {
                return trim((string) $row[$key]['value']);
            }
        }

        return '';
    }

    private function studentName(array $row): string
    {
        $direct = $this->value($row, ['student_name', 'full_name', 'name']);
        if ($direct !== '') {
            return $direct;
        }

        return trim($this->value($row, ['first_name']) . ' ' . $this->value($row, ['last_name']));
    }

    private function normalizeLevel(string $level, string $className): string
    {
        $level = strtolower(trim($level));
        if ($level !== '') {
            $level = str_replace(['-', ' '], '_', $level);
            $level = preg_replace('/[^a-z0-9_]+/', '', $level) ?? '';
            $level = preg_replace('/_+/', '_', $level) ?? '';
            $level = trim($level, '_');

            if (str_starts_with($level, 'creche')) {
                return 'creche';
            }
            if (str_starts_with($level, 'pre_nursery') || str_starts_with($level, 'prenursery')) {
                return 'pre_nursery';
            }

            return $level;
        }

        $class = strtolower($className);
        return match (true) {
            str_contains($class, 'creche') => 'creche',
            str_contains($class, 'pre nursery'), str_contains($class, 'pre-nursery'), str_contains($class, 'prenursery') => 'pre_nursery',
            str_contains($class, 'nursery'), str_contains($class, 'kg') => 'nursery',
            str_contains($class, 'primary'), str_contains($class, 'pry'), str_contains($class, 'basic') => 'primary',
            default => 'secondary',
        };
    }

    private function normalizeStatus(string $status): ?string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['active', 'inactive', 'graduated'], true) ? $status : null;
    }

    private function session(School $school, string $sessionName): AcademicSession
    {
        $normalized = trim($sessionName);
        $session = AcademicSession::query()
            ->where('school_id', (int) $school->id)
            ->whereRaw('LOWER(session_name) = ?', [strtolower($normalized)])
            ->first();

        if (!$session) {
            $session = AcademicSession::query()->create([
                'school_id' => (int) $school->id,
                'session_name' => $normalized,
                'academic_year' => $normalized,
                'status' => 'completed',
            ]);
            $this->summary['sessions_created']++;
        }

        $this->sessionOrder[(int) $session->id] = $this->sessionRank($normalized, (int) $session->id);
        asort($this->sessionOrder);

        return $session;
    }

    private function term(School $school, AcademicSession $session, string $termName): Term
    {
        $normalized = trim($termName) ?: 'First Term';
        $term = Term::query()
            ->where('school_id', (int) $school->id)
            ->where('academic_session_id', (int) $session->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($normalized)])
            ->first();

        if (!$term) {
            $term = Term::query()->create([
                'school_id' => (int) $school->id,
                'academic_session_id' => (int) $session->id,
                'name' => $normalized,
                'is_current' => false,
            ]);
            $this->summary['terms_created']++;
        }

        return $term;
    }

    private function schoolClass(School $school, AcademicSession $session, string $level, string $className): SchoolClass
    {
        $normalized = trim($className);
        $class = SchoolClass::query()
            ->where('school_id', (int) $school->id)
            ->where('academic_session_id', (int) $session->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($normalized)])
            ->first();

        if (!$class) {
            $class = SchoolClass::query()->create([
                'school_id' => (int) $school->id,
                'academic_session_id' => (int) $session->id,
                'level' => $level,
                'name' => $normalized,
            ]);
            $this->summary['classes_created']++;
        }

        return $class;
    }

    private function department(School $school, SchoolClass $class, string $name): ClassDepartment
    {
        $normalized = trim($name);
        $department = ClassDepartment::query()
            ->where('school_id', (int) $school->id)
            ->where('class_id', (int) $class->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($normalized)])
            ->first();

        if (!$department) {
            $department = ClassDepartment::query()->create([
                'school_id' => (int) $school->id,
                'class_id' => (int) $class->id,
                'name' => $normalized,
            ]);
            $this->summary['departments_created']++;
        }

        return $department;
    }

    private function student(School $school, array $row, string $name, string $level, ?string $status, int $actorUserId): Student
    {
        $identifier = $this->value($row, ['admission_no', 'admission_number', 'student_id', 'id_number', 'username']);
        $email = $this->value($row, ['email', 'student_email']);
        $gender = $this->value($row, ['gender', 'sex']);
        $cacheKey = strtolower($identifier ?: $email ?: $name);

        if (isset($this->studentsByKey[$cacheKey])) {
            return $this->studentsByKey[$cacheKey];
        }

        $userQuery = User::query()
            ->where('school_id', (int) $school->id)
            ->where('role', 'student');

        $user = null;
        if ($identifier !== '') {
            $user = (clone $userQuery)->whereRaw('LOWER(username) = ?', [strtolower($identifier)])->first();
        }
        if (!$user && $email !== '') {
            $user = (clone $userQuery)->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        }

        $isNewUser = false;
        $plainPassword = null;
        $effectiveStatus = $status ?: 'active';

        if (!$user) {
            $isNewUser = true;
            $plainPassword = Str::random(10);
            $user = User::query()->create([
                'school_id' => (int) $school->id,
                'name' => $name,
                'username' => $this->uniqueUsername($identifier ?: $name),
                'email' => $email !== '' ? $email : null,
                'password' => Hash::make($plainPassword),
                'role' => 'student',
                'is_active' => $effectiveStatus === 'active',
            ]);
        } else {
            $user->fill([
                'name' => $name,
                'email' => $email !== '' ? $email : $user->email,
            ]);
            $user->is_active = $effectiveStatus === 'active';
            $user->save();
        }

        if ($isNewUser && $plainPassword !== null) {
            $this->pendingPasswordsByUserId[(int) $user->id] = $plainPassword;
        }

        $student = Student::query()->firstOrNew([
            'school_id' => (int) $school->id,
            'user_id' => (int) $user->id,
        ]);
        $studentWasNew = !$student->exists;
        $student->fill([
            'school_id' => (int) $school->id,
            'user_id' => (int) $user->id,
            'education_level' => $level,
            'sex' => $gender !== '' ? $gender : $student->sex,
            'status' => $effectiveStatus,
        ]);
        $student->save();

        $this->summary[$studentWasNew ? 'students_created' : 'students_updated']++;
        $this->studentsByKey[$cacheKey] = $student;

        return $student;
    }

    private function uniqueUsername(string $seed): string
    {
        $seed = strtoupper(preg_replace('/[^a-z0-9]+/i', '', $seed) ?? '');
        $base = substr($seed, 0, 12) ?: 'STUDENT';

        if (!User::query()->whereRaw('LOWER(username) = ?', [strtolower($base)])->exists()) {
            return $base;
        }

        for ($attempt = 0; $attempt < 600; $attempt++) {
            $candidate = substr($base, 0, 8) . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (!User::query()->whereRaw('LOWER(username) = ?', [strtolower($candidate)])->exists()) {
                return $candidate;
            }
        }

        return substr($base, 0, 8) . strtoupper(Str::random(6));
    }

    private function enroll(
        School $school,
        AcademicSession $session,
        Term $term,
        SchoolClass $class,
        Student $student,
        ?ClassDepartment $department
    ): void {
        DB::table('class_students')->updateOrInsert([
            'school_id' => (int) $school->id,
            'academic_session_id' => (int) $session->id,
            'class_id' => (int) $class->id,
            'student_id' => (int) $student->id,
        ], [
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $where = [
            'student_id' => (int) $student->id,
            'class_id' => (int) $class->id,
            'term_id' => (int) $term->id,
        ];
        if (Schema::hasColumn('enrollments', 'school_id')) {
            $where['school_id'] = (int) $school->id;
        }

        $payload = [
            'department_id' => $department ? (int) $department->id : null,
            'updated_at' => now(),
        ];

        if (Enrollment::query()->where($where)->exists()) {
            Enrollment::query()->where($where)->update($payload);
            return;
        }

        Enrollment::query()->create([
            ...$where,
            'department_id' => $department ? (int) $department->id : null,
        ]);
    }

    private function rememberLatestPlacement(Student $student, AcademicSession $session, SchoolClass $class, ?string $declaredStatus): void
    {
        $studentId = (int) $student->id;
        $current = $this->latestPlacementByStudentId[$studentId] ?? null;
        $rank = $this->sessionOrder[(int) $session->id] ?? $this->sessionRank((string) $session->session_name, (int) $session->id);
        if ($current && (int) $current['session_rank'] > (int) $rank) {
            return;
        }

        $this->latestPlacementByStudentId[$studentId] = [
            'session_id' => (int) $session->id,
            'session_rank' => $rank,
            'class_name' => (string) $class->name,
            'level' => (string) $class->level,
            'declared_status' => $declaredStatus,
        ];
    }

    private function subjectRows(array $row): array
    {
        $subject = $this->value($row, ['subject', 'subject_name']);
        if ($subject !== '') {
            return [[
                'subject' => $subject,
                'ca' => $this->value($row, ['ca']),
                'exam' => $this->value($row, ['exam']),
                'score' => $this->value($row, ['score', 'total', 'total_score']),
            ]];
        }

        $subjects = [];
        foreach ($row as $key => $cell) {
            if (in_array($key, self::BASE_KEYS, true)) {
                continue;
            }
            $value = trim((string) ($cell['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $subjects[] = [
                'subject' => ucwords(str_replace('_', ' ', (string) ($cell['label'] ? $key : $key))),
                'score' => $value,
            ];
        }

        return $subjects;
    }

    private function scores(array $row): ?array
    {
        $ca = trim((string) ($row['ca'] ?? ''));
        $exam = trim((string) ($row['exam'] ?? ''));
        $score = trim((string) ($row['score'] ?? ''));

        if ($ca !== '' || $exam !== '') {
            if (($ca !== '' && !is_numeric($ca)) || ($exam !== '' && !is_numeric($exam))) {
                return null;
            }

            return [
                'ca' => $this->scoreNumber($ca),
                'exam' => $this->scoreNumber($exam),
            ];
        }

        if ($score === '' || !is_numeric($score)) {
            return null;
        }

        return [
            'ca' => 0,
            'exam' => $this->scoreNumber($score),
        ];
    }

    private function scoreNumber(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private function subject(School $school, string $name): Subject
    {
        $normalized = trim($name);
        $subject = Subject::query()
            ->where('school_id', (int) $school->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($normalized)])
            ->first();

        if (!$subject) {
            $subject = Subject::query()->create([
                'school_id' => (int) $school->id,
                'name' => $normalized,
            ]);
            $this->summary['subjects_created']++;
        }

        return $subject;
    }

    private function termSubject(Term $term, SchoolClass $class, Subject $subject, int $schoolId): TermSubject
    {
        $where = [
            'term_id' => (int) $term->id,
            'subject_id' => (int) $subject->id,
            'class_id' => (int) $class->id,
        ];

        $payload = [];
        if (Schema::hasColumn('term_subjects', 'school_id')) {
            $payload['school_id'] = $schoolId;
        }

        return TermSubject::query()->firstOrCreate($where, $payload);
    }

    private function finalizeStudentStatuses(School $school, int $actorUserId): void
    {
        $counts = [
            'active_students' => 0,
            'inactive_students' => 0,
            'graduated_students' => 0,
        ];

        foreach ($this->latestPlacementByStudentId as $studentId => $placement) {
            $declared = $placement['declared_status'];
            $status = $declared ?: ($this->hasNextClass($school, (string) $placement['class_name'], (string) $placement['level']) ? 'active' : 'graduated');
            $graduateSessionId = $status === 'graduated' ? (int) $placement['session_id'] : null;

            Student::query()
                ->where('school_id', (int) $school->id)
                ->where('id', (int) $studentId)
                ->update([
                    'status' => $status,
                    'graduated_at' => $status === 'graduated' ? now() : null,
                    'graduation_session_id' => $graduateSessionId,
                    'updated_at' => now(),
                ]);

            $userId = (int) Student::query()->where('id', (int) $studentId)->value('user_id');
            if ($userId) {
                User::query()
                    ->where('school_id', (int) $school->id)
                    ->where('id', $userId)
                    ->update([
                        'is_active' => $status === 'active',
                        'updated_at' => now(),
                    ]);

                if ($status === 'active' && isset($this->pendingPasswordsByUserId[$userId])) {
                    $user = User::query()->find($userId);
                    if ($user) {
                        UserCredentialStore::sync($user, $this->pendingPasswordsByUserId[$userId], $actorUserId);
                        $this->summary['logins_created']++;
                    }
                }
            }

            $counts[$status . '_students'] = ($counts[$status . '_students'] ?? 0) + 1;
        }

        $this->summary = array_merge($this->summary, $counts);
    }

    private function hasNextClass(School $school, string $className, string $level): bool
    {
        $templateAnswer = $this->hasNextClassFromTemplates($school, $className, $level);
        if ($templateAnswer !== null) {
            return $templateAnswer;
        }

        $rank = $this->classProgressionRank($className, $level);
        if ($rank === null) {
            return true;
        }

        return match (true) {
            $rank >= 1 && $rank < 3 => true,
            $rank === 3 => true,
            $rank >= 6 && $rank < 8 => true,
            $rank === 8 => true,
            $rank >= 11 && $rank < 13 => true,
            $rank === 13 => true,
            $rank >= 21 && $rank < 26 => true,
            $rank === 26 => true,
            $rank >= 31 && $rank < 33 => true,
            $rank === 33 => true,
            $rank >= 41 && $rank < 43 => true,
            default => false,
        };
    }

    private function hasNextClassFromTemplates(School $school, string $className, string $level): ?bool
    {
        $templates = ClassTemplateSchema::normalize($school->class_templates);
        $sequence = [];

        foreach (ClassTemplateSchema::activeSections($templates) as $section) {
            $sectionLevel = strtolower(trim((string) ($section['key'] ?? '')));
            if ($sectionLevel === '') {
                continue;
            }

            foreach (ClassTemplateSchema::activeClassNames($section) as $name) {
                $sequence[] = [
                    'level' => $sectionLevel,
                    'name' => strtolower(trim((string) $name)),
                ];
            }
        }

        if (empty($sequence)) {
            return null;
        }

        $needle = [
            'level' => strtolower(trim($level)),
            'name' => strtolower(trim($className)),
        ];

        foreach ($sequence as $index => $item) {
            if ($item === $needle) {
                return isset($sequence[$index + 1]);
            }
        }

        return null;
    }

    private function classProgressionRank(string $className, string $level): ?int
    {
        $name = strtolower(trim($className));
        $level = strtolower(trim($level));

        if (preg_match('/(?:creche)\D*(\d+)/i', $name, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(?:pre\s*[-_]?\s*nursery|prenursery)\D*(\d+)/i', $name, $m)) {
            return 5 + (int) $m[1];
        }
        if (preg_match('/(?:nursery|kg)\D*(\d+)/i', $name, $m)) {
            return 10 + (int) $m[1];
        }
        if (preg_match('/(?:primary|pry|basic)\D*(\d+)/i', $name, $m)) {
            return 20 + (int) $m[1];
        }
        if (preg_match('/(?:^|\b)(?:js|jss|junior\s*secondary)\D*(\d+)/i', $name, $m)) {
            return 30 + (int) $m[1];
        }
        if (preg_match('/(?:^|\b)(?:ss|sss|senior\s*secondary)\D*(\d+)/i', $name, $m)) {
            return 40 + (int) $m[1];
        }

        if ($level === 'secondary' && preg_match('/\b([1-3])\b/', $name, $m)) {
            return 40 + (int) $m[1];
        }

        return null;
    }

    private function skip(int $lineNumber, string $message): void
    {
        $this->summary['rows_skipped']++;
        $this->warn($lineNumber, $message);
    }

    private function warn(int $lineNumber, string $message): void
    {
        $this->warnings[] = "Line {$lineNumber}: {$message}";
    }

    private function sessionRank(string $sessionName, int $fallback): int
    {
        if (preg_match('/(20\d{2}|19\d{2})/', $sessionName, $match)) {
            return (int) $match[1];
        }

        return 100000 + $fallback;
    }
}
