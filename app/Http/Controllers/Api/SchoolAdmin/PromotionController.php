<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\LevelDepartment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotionController extends Controller
{
    // GET /api/school-admin/promotion/classes
    public function classes(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);

        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('level')
            ->orderBy('id')
            ->get(['id', 'name', 'level']);

        $levels = $classes
            ->groupBy('level')
            ->map(function ($items, $level) {
                return [
                    'level' => $level,
                    'classes' => $items->values(),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'current_session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'current_term' => [
                    'id' => (int) $term->id,
                    'name' => $term->name,
                ],
                'levels' => $levels,
            ],
        ]);
    }

    // GET /api/school-admin/promotion/classes/{class}/students
    public function classStudents(Request $request, SchoolClass $class)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $class->school_id === $schoolId, 403);

        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        if ((int) $class->academic_session_id !== (int) $session->id) {
            return response()->json([
                'message' => 'Promotion is only available for classes in the current academic session.',
            ], 422);
        }

        [$targetSession, $nextClass] = $this->resolvePromotionDestination($schoolId, $session, $class);

        $rows = DB::table('class_students as cs')
            ->join('students as s', 's.id', '=', 'cs.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('cs.school_id', $schoolId)
            ->where('cs.academic_session_id', $session->id)
            ->where('cs.class_id', $class->id)
            ->select([
                'cs.student_id',
                'u.name',
                'u.email',
            ])
            ->orderBy('u.name')
            ->get();

        $alreadyInNextClass = [];
        if ($nextClass && $targetSession) {
            $alreadyInNextClass = DB::table('class_students')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $targetSession->id)
                ->where('class_id', $nextClass->id)
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();
        }

        $students = $rows->values()->map(function ($row, $index) use ($nextClass, $targetSession, $alreadyInNextClass) {
            $isAlreadyPromoted = isset($alreadyInNextClass[(int) $row->student_id]);
            return [
                'sn' => $index + 1,
                'student_id' => (int) $row->student_id,
                'name' => $row->name,
                'email' => $row->email,
                'can_promote' => $nextClass !== null && !$isAlreadyPromoted,
                'next_class' => $nextClass ? [
                    'id' => (int) $nextClass->id,
                    'name' => $nextClass->name,
                    'level' => $nextClass->level,
                ] : null,
                'next_session' => $targetSession ? [
                    'id' => (int) $targetSession->id,
                    'session_name' => $targetSession->session_name,
                    'academic_year' => $targetSession->academic_year,
                    'status' => $targetSession->status,
                ] : null,
                'already_promoted' => $isAlreadyPromoted,
            ];
        });

        return response()->json([
            'data' => [
                'class' => [
                    'id' => (int) $class->id,
                    'name' => $class->name,
                    'level' => $class->level,
                ],
                'next_class' => $nextClass ? [
                    'id' => (int) $nextClass->id,
                    'name' => $nextClass->name,
                    'level' => $nextClass->level,
                ] : null,
                'next_session' => $targetSession ? [
                    'id' => (int) $targetSession->id,
                    'session_name' => $targetSession->session_name,
                    'academic_year' => $targetSession->academic_year,
                    'status' => $targetSession->status,
                ] : null,
                'students' => $students,
            ],
        ]);
    }

    // POST /api/school-admin/promotion/classes/{class}/students/{student}/promote
    public function promote(Request $request, SchoolClass $class, Student $student)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $class->school_id === $schoolId, 403);
        abort_unless((int) $student->school_id === $schoolId, 403);

        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        if ((int) $class->academic_session_id !== (int) $session->id) {
            return response()->json([
                'message' => 'Promotion is only available for classes in the current academic session.',
            ], 422);
        }

        [$targetSession, $nextClass] = $this->resolvePromotionDestination($schoolId, $session, $class);

        if (!$targetSession || !$nextClass) {
            return response()->json([
                'message' => 'No next class available in the destination academic session for promotion.',
            ], 422);
        }

        $currentClassEnrollment = DB::table('class_students')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$currentClassEnrollment) {
            return response()->json([
                'message' => 'Student is not enrolled in the selected class.',
            ], 404);
        }

        $sourceDepartmentId = $this->resolveSourceDepartmentId(
            $schoolId,
            (int) $session->id,
            (int) $term->id,
            (int) $class->id,
            (int) $student->id
        );
        $targetDepartmentId = $this->resolveTargetDepartmentId($schoolId, $nextClass, $sourceDepartmentId);

        DB::transaction(function () use (
            $schoolId,
            $session,
            $targetSession,
            $class,
            $nextClass,
            $student,
            $currentClassEnrollment,
            $targetDepartmentId
        ) {
            $targetSessionTermIds = Term::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $targetSession->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $alreadyInNextClass = DB::table('class_students')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $targetSession->id)
                ->where('class_id', $nextClass->id)
                ->where('student_id', $student->id)
                ->exists();

            if (!$alreadyInNextClass) {
                DB::table('class_students')->insert([
                    'school_id' => $schoolId,
                    'academic_session_id' => $targetSession->id,
                    'class_id' => $nextClass->id,
                    'student_id' => $student->id,
                    'roll_number' => $currentClassEnrollment->roll_number ?? null,
                    'enrolled_at' => $currentClassEnrollment->enrolled_at ?? now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ((int) $targetSession->id === (int) $session->id) {
                DB::table('class_students')
                    ->where('school_id', $schoolId)
                    ->where('academic_session_id', $session->id)
                    ->where('class_id', $class->id)
                    ->where('student_id', $student->id)
                    ->delete();
            }

            foreach ($targetSessionTermIds as $termId) {
                $targetEnrollmentQuery = DB::table('enrollments')
                    ->where('student_id', $student->id)
                    ->where('class_id', $nextClass->id)
                    ->where('term_id', $termId);
                if (Schema::hasColumn('enrollments', 'school_id')) {
                    $targetEnrollmentQuery->where('school_id', $schoolId);
                }

                if ($targetEnrollmentQuery->exists()) {
                    if ($targetDepartmentId !== null) {
                        $targetEnrollmentQuery->update([
                            'department_id' => $targetDepartmentId,
                            'updated_at' => now(),
                        ]);
                    }
                    continue;
                }

                if ((int) $targetSession->id === (int) $session->id) {
                    $oldEnrollmentQuery = DB::table('enrollments')
                        ->where('student_id', $student->id)
                        ->where('class_id', $class->id)
                        ->where('term_id', $termId);
                    if (Schema::hasColumn('enrollments', 'school_id')) {
                        $oldEnrollmentQuery->where('school_id', $schoolId);
                    }

                    $updated = $oldEnrollmentQuery->update([
                        'class_id' => $nextClass->id,
                        'department_id' => $targetDepartmentId,
                        'updated_at' => now(),
                    ]);
                    if ($updated > 0) {
                        continue;
                    }
                }

                $insertData = [
                    'student_id' => $student->id,
                    'class_id' => $nextClass->id,
                    'term_id' => $termId,
                    'department_id' => $targetDepartmentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('enrollments', 'school_id')) {
                    $insertData['school_id'] = $schoolId;
                }
                DB::table('enrollments')->insert($insertData);
            }
        });

        return response()->json([
            'message' => 'Student promoted successfully.',
            'data' => [
                'student_id' => (int) $student->id,
                'from_class' => [
                    'id' => (int) $class->id,
                    'name' => $class->name,
                    'level' => $class->level,
                ],
                'to_class' => [
                    'id' => (int) $nextClass->id,
                    'name' => $nextClass->name,
                    'level' => $nextClass->level,
                ],
                'to_session' => [
                    'id' => (int) $targetSession->id,
                    'session_name' => $targetSession->session_name,
                    'academic_year' => $targetSession->academic_year,
                    'status' => $targetSession->status,
                ],
            ],
        ]);
    }

    private function resolvePromotionDestination(int $schoolId, AcademicSession $sourceSession, SchoolClass $sourceClass): array
    {
        $targetSession = $this->resolveTargetPromotionSession($schoolId, (int) $sourceSession->id);
        if (!$targetSession) {
            return [null, null];
        }

        $nextClass = $this->resolveNextClassInSession($schoolId, (int) $targetSession->id, $sourceClass);
        return [$targetSession, $nextClass];
    }

    private function resolveTargetPromotionSession(int $schoolId, int $sourceSessionId): ?AcademicSession
    {
        $pendingSession = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
        if ($pendingSession && (int) $pendingSession->id !== $sourceSessionId) {
            return $pendingSession;
        }

        $currentSession = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->orderByDesc('id')
            ->first();
        if ($currentSession) {
            return $currentSession;
        }

        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->first();
    }

    private function resolveNextClassInSession(int $schoolId, int $targetSessionId, SchoolClass $sourceClass): ?SchoolClass
    {
        $classes = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $targetSessionId)
            ->get(['id', 'name', 'level']);

        if ($classes->isEmpty()) {
            return null;
        }

        $sourceRank = $this->classProgressionRank((string) $sourceClass->name, (string) $sourceClass->level);
        $targetRank = $this->nextClassProgressionRank($sourceRank);

        if ($targetRank !== null) {
            $matched = $classes->first(function (SchoolClass $class) use ($targetRank) {
                return $this->classProgressionRank((string) $class->name, (string) $class->level) === $targetRank;
            });
            if ($matched) {
                return $matched;
            }
        }

        if ($sourceRank !== null) {
            $matched = $classes
                ->map(function (SchoolClass $class) {
                    return [
                        'class' => $class,
                        'rank' => $this->classProgressionRank((string) $class->name, (string) $class->level),
                    ];
                })
                ->filter(fn ($item) => $item['rank'] !== null && $item['rank'] > $sourceRank)
                ->sortBy('rank')
                ->first();
            if ($matched) {
                return $matched['class'];
            }
        }

        return $classes
            ->where('level', $sourceClass->level)
            ->sortBy('id')
            ->first();
    }

    private function classProgressionRank(string $className, string $classLevel = ''): ?int
    {
        $name = strtolower(trim($className));
        $level = strtolower(trim($classLevel));

        if (preg_match('/nursery\D*(\d+)/i', $name, $m)) {
            return 10 + (int) $m[1];
        }
        if (preg_match('/primary\D*(\d+)/i', $name, $m)) {
            return 20 + (int) $m[1];
        }
        if (preg_match('/(?:^|\b)(?:js|jss|junior\s*secondary)\D*(\d+)/i', $name, $m)) {
            return 30 + (int) $m[1];
        }
        if (preg_match('/(?:^|\b)(?:ss|sss|senior\s*secondary)\D*(\d+)/i', $name, $m)) {
            return 40 + (int) $m[1];
        }
        if (preg_match('/grade\D*(\d+)/i', $name, $m)) {
            return 20 + (int) $m[1];
        }

        if ($level === 'nursery') {
            return 10;
        }
        if ($level === 'primary') {
            return 20;
        }
        if ($level === 'secondary') {
            return 30;
        }

        return null;
    }

    private function nextClassProgressionRank(?int $currentRank): ?int
    {
        if ($currentRank === null) {
            return null;
        }

        return match (true) {
            $currentRank >= 11 && $currentRank < 13 => $currentRank + 1,
            $currentRank === 13 => 21,
            $currentRank >= 21 && $currentRank < 26 => $currentRank + 1,
            $currentRank === 26 => 31,
            $currentRank >= 31 && $currentRank < 33 => $currentRank + 1,
            $currentRank === 33 => 41,
            $currentRank >= 41 && $currentRank < 43 => $currentRank + 1,
            default => null,
        };
    }

    private function resolveSourceDepartmentId(
        int $schoolId,
        int $sessionId,
        int $currentTermId,
        int $classId,
        int $studentId
    ): ?int {
        $query = DB::table('enrollments')
            ->join('terms', 'terms.id', '=', 'enrollments.term_id')
            ->where('terms.school_id', $schoolId)
            ->where('terms.academic_session_id', $sessionId)
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.class_id', $classId)
            ->whereNotNull('enrollments.department_id')
            ->orderByRaw('CASE WHEN enrollments.term_id = ? THEN 0 ELSE 1 END', [$currentTermId])
            ->orderByDesc('enrollments.id');

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $query->where('enrollments.school_id', $schoolId);
        }

        $departmentId = $query->value('enrollments.department_id');
        return $departmentId ? (int) $departmentId : null;
    }

    private function resolveTargetDepartmentId(int $schoolId, SchoolClass $targetClass, ?int $sourceDepartmentId): ?int
    {
        if (!$sourceDepartmentId) {
            return null;
        }

        $sourceDepartment = ClassDepartment::query()
            ->where('id', $sourceDepartmentId)
            ->where('school_id', $schoolId)
            ->first(['id', 'name']);
        if (!$sourceDepartment || trim((string) $sourceDepartment->name) === '') {
            return null;
        }

        $this->syncClassDepartmentsFromLevel($schoolId, $targetClass);

        $targetDepartment = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $targetClass->id)
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $sourceDepartment->name)])
            ->first();

        if ($targetDepartment) {
            return (int) $targetDepartment->id;
        }

        $created = ClassDepartment::create([
            'school_id' => $schoolId,
            'class_id' => $targetClass->id,
            'name' => (string) $sourceDepartment->name,
        ]);

        return (int) $created->id;
    }

    private function syncClassDepartmentsFromLevel(int $schoolId, SchoolClass $class): void
    {
        $levelDepartments = LevelDepartment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('level', $class->level)
            ->get(['name']);

        foreach ($levelDepartments as $department) {
            ClassDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'class_id' => $class->id,
                'name' => (string) $department->name,
            ]);
        }
    }

    private function resolveCurrentSessionAndTerm(int $schoolId): array
    {
        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();
        if (!$session) {
            return [null, null];
        }

        $term = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('is_current', true)
            ->first();
        if (!$term) {
            $term = Term::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->orderBy('id')
                ->first();
        }

        return [$session, $term];
    }
}
