<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\School;
use App\Models\Term;
use App\Models\TermSubject;
use App\Support\AssessmentSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TeacherResultsController extends Controller
{
  // GET /api/staff/results/subjects
  public function mySubjects(Request $request)
  {
    $user = $request->user();
    $schoolId = $user->school_id;
    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    if (!$session) return response()->json(['data' => []]);

    $currentTerm = Term::where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->where('is_current', true)
      ->first();
    if (!$currentTerm) return response()->json(['data' => []]);

    $items = TermSubject::query()
      ->where('term_subjects.school_id', $schoolId)
      ->where('term_subjects.teacher_user_id', $user->id)
      ->where('term_subjects.term_id', $currentTerm->id)
      ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->select([
        'term_subjects.id as term_subject_id',
        'subjects.name as subject_name',
        'subjects.code as subject_code',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.name as term_name',
        'term_subjects.class_id',
        'term_subjects.term_id',
        'term_subjects.subject_id',
      ])
      ->orderBy('classes.level')
      ->orderBy('classes.name')
      ->orderBy('subjects.name')
      ->get();

    return response()->json(['data' => $items]);
  }

  // GET /api/staff/results/subjects/{termSubject}/students
  public function subjectStudents(Request $request, TermSubject $termSubject)
  {
    $user = $request->user();
    $schoolId = $user->school_id;
    $school = School::find((int) $schoolId);
    $assessmentSchema = AssessmentSchema::normalizeSchema($school?->assessment_schema);

    abort_unless((int)$termSubject->school_id === (int)$schoolId, 403);
    abort_unless((int)$termSubject->teacher_user_id === (int)$user->id, 403);

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;
    abort_unless($currentTerm && (int)$termSubject->term_id === (int)$currentTerm->id, 403);

    $subjectSummary = TermSubject::query()
      ->where('term_subjects.id', $termSubject->id)
      ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->select([
        'subjects.name as subject_name',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.name as term_name',
      ])
      ->first();

    // students enrolled in the same class + term
    $rowsQuery = Enrollment::query()
      ->where('enrollments.class_id', $termSubject->class_id)
      ->where('enrollments.term_id', $termSubject->term_id)
      ->join('students', 'students.id', '=', 'enrollments.student_id')
      ->join('users', 'users.id', '=', 'students.user_id')
      ->leftJoin('results', function ($join) use ($termSubject) {
        $join->on('results.student_id', '=', 'students.id')
          ->where('results.term_subject_id', '=', $termSubject->id);
      })
      ->when(Schema::hasTable('student_subject_exclusions'), function ($query) use ($schoolId, $termSubject, $session) {
        $query->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $termSubject, $session) {
          $join->on('student_subject_exclusions.student_id', '=', 'students.id')
            ->where('student_subject_exclusions.school_id', '=', $schoolId)
            ->where('student_subject_exclusions.academic_session_id', '=', (int) $session->id)
            ->where('student_subject_exclusions.class_id', '=', (int) $termSubject->class_id)
            ->where('student_subject_exclusions.subject_id', '=', (int) $termSubject->subject_id);
        })
        ->whereNull('student_subject_exclusions.id');
      })
      ->select([
        'students.id as student_id',
        'users.name as student_name',
        DB::raw('COALESCE(results.ca, 0) as ca'),
        'results.ca_breakdown',
        DB::raw('COALESCE(results.exam, 0) as exam'),
      ])
      ->orderBy('users.name');

    if (Schema::hasColumn('enrollments', 'school_id')) {
      $rowsQuery->where('enrollments.school_id', $schoolId);
    }

    $rows = $rowsQuery
      ->get()
      ->map(function ($r) use ($assessmentSchema) {
        $caBreakdown = AssessmentSchema::normalizeBreakdown(
          $r->ca_breakdown ?? null,
          $assessmentSchema,
          (int) ($r->ca ?? 0)
        );
        $caTotal = AssessmentSchema::breakdownTotal($caBreakdown);
        $exam = max(0, min((int) $assessmentSchema['exam_max'], (int) ($r->exam ?? 0)));
        $total = $caTotal + $exam;
        return [
          'student_id' => $r->student_id,
          'student_name' => $r->student_name,
          'ca' => $caTotal,
          'ca_breakdown' => $caBreakdown,
          'exam' => $exam,
          'total' => $total,
          'grade' => $this->gradeFromTotal($total),
        ];
      });

    return response()->json([
      'data' => [
        'term_subject_id' => $termSubject->id,
        'subject_name' => (string) ($subjectSummary->subject_name ?? ''),
        'class_name' => (string) ($subjectSummary->class_name ?? ''),
        'class_level' => (string) ($subjectSummary->class_level ?? ''),
        'term_name' => (string) ($subjectSummary->term_name ?? ''),
        'assessment_schema' => $assessmentSchema,
        'students' => $rows,
      ]
    ]);
  }

  // POST /api/staff/results/subjects/{termSubject}/scores
  // payload: { scores: [{student_id, ca, exam}, ...] }
  public function saveScores(Request $request, TermSubject $termSubject)
  {
    $user = $request->user();
    $schoolId = $user->school_id;
    $school = School::find((int) $schoolId);
    $assessmentSchema = AssessmentSchema::normalizeSchema($school?->assessment_schema);
    $caMaxes = $assessmentSchema['ca_maxes'];
    $examMax = (int) $assessmentSchema['exam_max'];
    $caTotalMax = array_sum($caMaxes);

    abort_unless((int)$termSubject->school_id === (int)$schoolId, 403);
    abort_unless((int)$termSubject->teacher_user_id === (int)$user->id, 403);

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;
    abort_unless($currentTerm && (int)$termSubject->term_id === (int)$currentTerm->id, 403);

    $payload = $request->validate([
      'scores' => 'required|array|min:1',
      'scores.*.student_id' => 'required|integer',
      'scores.*.ca' => 'nullable|integer|min:0|max:100',
      'scores.*.ca_breakdown' => 'nullable|array|size:5',
      'scores.*.ca_breakdown.*' => 'nullable|integer|min:0|max:100',
      'scores.*.exam' => 'required|integer|min:0|max:100',
    ]);

    $eligibleStudentIdsQuery = Enrollment::query()
      ->where('class_id', $termSubject->class_id)
      ->where('term_id', $termSubject->term_id)
      ->select('student_id');
    if (Schema::hasColumn('enrollments', 'school_id')) {
      $eligibleStudentIdsQuery->where('school_id', $schoolId);
    }
    if (Schema::hasTable('student_subject_exclusions')) {
      $eligibleStudentIdsQuery->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $termSubject, $session) {
        $join->on('student_subject_exclusions.student_id', '=', 'enrollments.student_id')
          ->where('student_subject_exclusions.school_id', '=', $schoolId)
          ->where('student_subject_exclusions.academic_session_id', '=', (int) $session->id)
          ->where('student_subject_exclusions.class_id', '=', (int) $termSubject->class_id)
          ->where('student_subject_exclusions.subject_id', '=', (int) $termSubject->subject_id);
      })
      ->whereNull('student_subject_exclusions.id');
    }
    $eligibleStudentIds = $eligibleStudentIdsQuery
      ->distinct()
      ->pluck('student_id')
      ->map(fn ($id) => (int) $id)
      ->all();

    $preparedScores = [];
    $errors = [];

    foreach ($payload['scores'] as $idx => $score) {
      $rawBreakdown = $score['ca_breakdown'] ?? null;
      $legacyCa = (int) ($score['ca'] ?? 0);

      if (is_array($rawBreakdown)) {
        for ($i = 0; $i < 5; $i++) {
          $inputVal = (int) ($rawBreakdown[$i] ?? 0);
          if ($inputVal > (int) $caMaxes[$i]) {
            $errors["scores.$idx.ca_breakdown.$i"] = [
              'CA' . ($i + 1) . " score cannot be more than {$caMaxes[$i]}.",
            ];
          }
        }
      } elseif ($legacyCa > $caTotalMax) {
        $errors["scores.$idx.ca"] = [
          "CA score cannot be more than {$caTotalMax}.",
        ];
      }

      $caBreakdown = AssessmentSchema::normalizeBreakdown($rawBreakdown, $assessmentSchema, $legacyCa);
      $caTotal = AssessmentSchema::breakdownTotal($caBreakdown);
      $exam = (int) ($score['exam'] ?? 0);
      $studentId = (int) $score['student_id'];

      if (!in_array($studentId, $eligibleStudentIds, true)) {
        $errors["scores.$idx.student_id"] = [
          'Student is not eligible for this subject in the current term.',
        ];
      }

      if ($exam > $examMax) {
        $errors["scores.$idx.exam"] = [
          "Exam score cannot be more than {$examMax}.",
        ];
      }

      if (($caTotal + $exam) > 100) {
        $errors["scores.$idx.total"] = [
          'CA total and exam score cannot be more than 100.',
        ];
      }

      $preparedScores[] = [
        'student_id' => $studentId,
        'ca' => $caTotal,
        'ca_breakdown' => $caBreakdown,
        'exam' => $exam,
      ];
    }

    if (!empty($errors)) {
      throw ValidationException::withMessages($errors);
    }

    DB::transaction(function () use ($preparedScores, $termSubject, $schoolId) {
      foreach ($preparedScores as $score) {
        Result::updateOrCreate(
          [
            'school_id' => $schoolId,
            'term_subject_id' => $termSubject->id,
            'student_id' => $score['student_id'],
          ],
          [
            'ca' => $score['ca'],
            'ca_breakdown' => $score['ca_breakdown'],
            'exam' => $score['exam'],
          ]
        );
      }
    });

    return response()->json(['message' => 'Scores saved successfully']);
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
}
