<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
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

        $nextClass = SchoolClass::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('level', $class->level)
            ->where('id', '>', $class->id)
            ->orderBy('id')
            ->first(['id', 'name', 'level']);

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

        $students = $rows->values()->map(function ($row, $index) use ($nextClass) {
            return [
                'sn' => $index + 1,
                'student_id' => (int) $row->student_id,
                'name' => $row->name,
                'email' => $row->email,
                'can_promote' => $nextClass !== null,
                'next_class' => $nextClass ? [
                    'id' => (int) $nextClass->id,
                    'name' => $nextClass->name,
                    'level' => $nextClass->level,
                ] : null,
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

        $nextClass = SchoolClass::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('level', $class->level)
            ->where('id', '>', $class->id)
            ->orderBy('id')
            ->first();

        if (!$nextClass) {
            return response()->json([
                'message' => 'No next class available for promotion from this class.',
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

        DB::transaction(function () use (
            $schoolId,
            $session,
            $term,
            $class,
            $nextClass,
            $student,
            $currentClassEnrollment
        ) {
            $alreadyInNextClass = DB::table('class_students')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('class_id', $nextClass->id)
                ->where('student_id', $student->id)
                ->exists();

            if (!$alreadyInNextClass) {
                DB::table('class_students')->insert([
                    'school_id' => $schoolId,
                    'academic_session_id' => $session->id,
                    'class_id' => $nextClass->id,
                    'student_id' => $student->id,
                    'roll_number' => $currentClassEnrollment->roll_number ?? null,
                    'enrolled_at' => $currentClassEnrollment->enrolled_at ?? now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('class_students')
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->delete();

            $enrollmentQuery = DB::table('enrollments')
                ->where('student_id', $student->id)
                ->where('class_id', $class->id)
                ->where('term_id', $term->id);

            if (Schema::hasColumn('enrollments', 'school_id')) {
                $enrollmentQuery->where('school_id', $schoolId);
            }

            $enrollmentQuery->update(['class_id' => $nextClass->id]);
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
            ],
        ]);
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

