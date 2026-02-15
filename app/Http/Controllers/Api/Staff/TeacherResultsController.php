<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\Term;
use App\Models\TermSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    abort_unless((int)$termSubject->school_id === (int)$schoolId, 403);
    abort_unless((int)$termSubject->teacher_user_id === (int)$user->id, 403);

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;
    abort_unless($currentTerm && (int)$termSubject->term_id === (int)$currentTerm->id, 403);

    // students enrolled in the same class + term
    $rows = Enrollment::query()
      ->where('enrollments.class_id', $termSubject->class_id)
      ->where('enrollments.term_id', $termSubject->term_id)
      ->join('students', 'students.id', '=', 'enrollments.student_id')
      ->join('users', 'users.id', '=', 'students.user_id')
      ->leftJoin('results', function ($join) use ($termSubject) {
        $join->on('results.student_id', '=', 'students.id')
          ->where('results.term_subject_id', '=', $termSubject->id);
      })
      ->select([
        'students.id as student_id',
        'users.name as student_name',
        DB::raw('COALESCE(results.ca, 0) as ca'),
        DB::raw('COALESCE(results.exam, 0) as exam'),
      ])
      ->orderBy('users.name')
      ->get()
      ->map(function ($r) {
        $total = (int)$r->ca + (int)$r->exam;
        return [
          'student_id' => $r->student_id,
          'student_name' => $r->student_name,
          'ca' => (int)$r->ca,
          'exam' => (int)$r->exam,
          'total' => $total,
          'grade' => $this->gradeFromTotal($total),
        ];
      });

    return response()->json([
      'data' => [
        'term_subject_id' => $termSubject->id,
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
      'scores.*.ca' => 'required|integer|min:0|max:30',
      'scores.*.exam' => 'required|integer|min:0|max:70',
    ]);

    DB::transaction(function () use ($payload, $termSubject, $schoolId) {
      foreach ($payload['scores'] as $s) {
        Result::updateOrCreate(
          [
            'school_id' => $schoolId,
            'term_subject_id' => $termSubject->id,
            'student_id' => $s['student_id'],
          ],
          [
            'ca' => $s['ca'],
            'exam' => $s['exam'],
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
