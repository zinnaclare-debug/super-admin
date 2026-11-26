<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentBehaviourRating;
use App\Models\Term;
use App\Models\TermSubject;
use App\Support\AssessmentSchema;
use App\Support\ClassicResultComponents;
use App\Support\ClassTemplateSchema;
use App\Support\CommentOnlySchema;
use App\Support\GradingSchema;
use App\Support\ResultPdfTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TeacherResultsController extends Controller
{
  private function hasResultCaBreakdownColumn(): bool
  {
    return Schema::hasColumn('results', 'ca_breakdown');
  }

  private function hasResultTeacherCommentColumn(): bool
  {
    return Schema::hasColumn('results', 'teacher_comment');
  }

  private function hasClassicComponentsColumn(): bool
  {
    return Schema::hasColumn('results', 'classic_components');
  }

  private function hasAssessmentProfileColumn(): bool
  {
    return Schema::hasColumn('results', 'assessment_profile');
  }

  private function canUseStudentSubjectExclusions(): bool
  {
    if (!Schema::hasTable('student_subject_exclusions')) {
      return false;
    }

    $required = [
      'student_id',
      'school_id',
      'academic_session_id',
      'class_id',
      'subject_id',
    ];

    foreach ($required as $column) {
      if (!Schema::hasColumn('student_subject_exclusions', $column)) {
        return false;
      }
    }

    return true;
  }

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

    $school = School::find((int) $schoolId);
    $numericSubjects = $items
      ->reject(fn ($item) => ClassTemplateSchema::commentsOnlyForClass(
        $school?->class_templates,
        $item->class_level ?? null,
        $item->class_name ?? null
      ))
      ->values();

    // Comments-only classes have one class assessment sheet, not a list of legacy subjects.
    $assessmentSheets = SchoolClass::query()
      ->where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->where('class_teacher_user_id', $user->id)
      ->orderBy('level')
      ->orderBy('name')
      ->get(['id', 'name', 'level'])
      ->filter(fn (SchoolClass $class) => ClassTemplateSchema::commentsOnlyForClass(
        $school?->class_templates,
        $class->level,
        $class->name
      ))
      ->map(fn (SchoolClass $class) => [
        'entry_type' => 'comments_only',
        'term_subject_id' => null,
        'subject_name' => 'Assessment Sheet',
        'subject_code' => null,
        'class_name' => (string) $class->name,
        'class_level' => (string) $class->level,
        'term_name' => (string) $currentTerm->name,
        'class_id' => (int) $class->id,
        'term_id' => (int) $currentTerm->id,
        'subject_id' => null,
      ]);

    return response()->json(['data' => $numericSubjects->concat($assessmentSheets)->values()]);
  }

  // GET /api/staff/results/subjects/{termSubject}/students
  public function subjectStudents(Request $request, TermSubject $termSubject)
  {
    $user = $request->user();
    $schoolId = $user->school_id;
    $school = School::find((int) $schoolId);

    $hasCaBreakdown = $this->hasResultCaBreakdownColumn();
    $hasTeacherComment = $this->hasResultTeacherCommentColumn();
    $hasClassicComponents = $this->hasClassicComponentsColumn();
    $hasAssessmentProfile = $this->hasAssessmentProfileColumn();
    $usesClassicComponents = false;
    $canUseExclusions = $this->canUseStudentSubjectExclusions();

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
    $assessmentSchema = $this->assessmentSchemaForTermSubject($school, $termSubject);
    abort_unless(! (bool) ($assessmentSchema['comments_only'] ?? false), 404);
    $usesClassicComponents = AssessmentSchema::usesClassicComponents($assessmentSchema);

    // Build the class list from current term enrollments first, then layer exclusions/results on top.
    $canUseDepartments = Schema::hasTable('class_departments')
      && Schema::hasColumn('enrollments', 'department_id');

    $studentQuery = Enrollment::query()
      ->join('students', 'students.id', '=', 'enrollments.student_id')
      ->join('users', 'users.id', '=', 'students.user_id')
      ->where('enrollments.class_id', (int) $termSubject->class_id)
      ->where('enrollments.term_id', (int) $termSubject->term_id)
      ->where('students.school_id', $schoolId)
      ->select([
        'students.id as student_id',
        'users.name as student_name',
        ...($canUseDepartments ? [
          'class_departments.id as department_id',
          'class_departments.name as department_name',
        ] : [
          DB::raw('NULL as department_id'),
          DB::raw('NULL as department_name'),
        ]),
      ])
      ->distinct()
      ->orderBy('users.name');

    if ($canUseDepartments) {
      $studentQuery->leftJoin('class_departments', 'class_departments.id', '=', 'enrollments.department_id');
    }

    if (Schema::hasColumn('enrollments', 'school_id')) {
      $studentQuery->where('enrollments.school_id', $schoolId);
    }

    if ($canUseExclusions) {
      $studentQuery->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $termSubject, $session) {
        $join->on('student_subject_exclusions.student_id', '=', 'enrollments.student_id')
          ->where('student_subject_exclusions.school_id', '=', $schoolId)
          ->where('student_subject_exclusions.academic_session_id', '=', (int) $session->id)
          ->where('student_subject_exclusions.class_id', '=', (int) $termSubject->class_id)
          ->where('student_subject_exclusions.subject_id', '=', (int) $termSubject->subject_id);
      })
      ->whereNull('student_subject_exclusions.student_id');
    }

    $students = $studentQuery->get();
    $studentIds = $students->pluck('student_id')->map(fn ($id) => (int) $id)->all();

    $resultsByStudentId = [];
    if (!empty($studentIds)) {
      $resultsQuery = Result::query()
        ->where('results.school_id', $schoolId)
        ->where('results.term_subject_id', (int) $termSubject->id)
        ->whereIn('results.student_id', $studentIds)
        ->select([
          'results.student_id',
          'results.ca',
          ...($hasCaBreakdown ? ['results.ca_breakdown'] : [DB::raw('NULL as ca_breakdown')]),
          'results.exam',
          ...($hasTeacherComment ? ['results.teacher_comment'] : [DB::raw('NULL as teacher_comment')]),
          ...($hasClassicComponents ? ['results.classic_components'] : [DB::raw('NULL as classic_components')]),
          ...($hasAssessmentProfile ? ['results.assessment_profile'] : [DB::raw('NULL as assessment_profile')]),
        ]);

      $resultsByStudentId = $resultsQuery
        ->get()
        ->keyBy(fn ($row) => (int) $row->student_id)
        ->all();
    }

    $rows = $students
      ->map(function ($student) use ($assessmentSchema, $resultsByStudentId, $schoolId, $usesClassicComponents) {
        $resultRow = $resultsByStudentId[(int) $student->student_id] ?? null;
        $rowSchema = $this->entrySchemaForResult($resultRow, $assessmentSchema);
        $caBreakdown = AssessmentSchema::normalizeBreakdown($resultRow->ca_breakdown ?? null, $rowSchema, (float) ($resultRow->ca ?? 0));
        $caTotal = AssessmentSchema::breakdownTotal($caBreakdown);
        $exam = max(0, min((float) $rowSchema['exam_max'], (float) ($resultRow->exam ?? 0)));
        $total = AssessmentSchema::totalFromScores($caBreakdown, $caTotal, $exam, $resultRow->classic_components ?? null, $rowSchema);

        return [
          'student_id' => (int) $student->student_id,
          'student_name' => (string) $student->student_name,
          'department_id' => isset($student->department_id) ? (int) $student->department_id : null,
          'department_name' => isset($student->department_name) ? (string) $student->department_name : '',
          'ca' => $caTotal,
          'ca_breakdown' => $caBreakdown,
          'exam' => $exam,
          'total' => $total,
          'grade' => $this->gradeFromTotal($schoolId, $total),
          'teacher_comment' => trim((string) ($resultRow->teacher_comment ?? '')),
          'classic_components' => $usesClassicComponents
            ? ClassicResultComponents::normalize($resultRow->classic_components ?? null, $rowSchema)
            : null,
        ];
      })
      ->values();

    return response()->json([
      'data' => [
        'term_subject_id' => $termSubject->id,
        'subject_name' => (string) ($subjectSummary->subject_name ?? ''),
        'class_name' => (string) ($subjectSummary->class_name ?? ''),
        'class_level' => (string) ($subjectSummary->class_level ?? ''),
        'term_name' => (string) ($subjectSummary->term_name ?? ''),
        'assessment_schema' => $assessmentSchema,
        'grading_schema' => GradingSchema::normalize($school?->grading_schema),
        'results_published' => $school?->resultsArePublished() ?? false,
        'uses_classic_components' => $usesClassicComponents,
        'departments' => $rows
          ->map(fn ($row) => [
            'id' => (int) ($row['department_id'] ?? 0),
            'name' => trim((string) ($row['department_name'] ?? '')),
          ])
          ->filter(fn ($row) => $row['id'] > 0 && $row['name'] !== '')
          ->unique(fn ($row) => $row['id'])
          ->values(),
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
    $assessmentSchema = $this->assessmentSchemaForTermSubject($school, $termSubject);
    $caMaxes = $assessmentSchema['ca_maxes'];
    $examMax = (float) $assessmentSchema['exam_max'];
    $caTotalMax = array_sum($caMaxes);

    $hasCaBreakdown = $this->hasResultCaBreakdownColumn();
    $hasTeacherComment = $this->hasResultTeacherCommentColumn();
    $hasClassicComponents = $this->hasClassicComponentsColumn();
    $usesClassicComponents = AssessmentSchema::usesClassicComponents($assessmentSchema);
    $canUseExclusions = $this->canUseStudentSubjectExclusions();
    $caSlotCount = max(1, count($caMaxes));
    $commentsOnly = (bool) ($assessmentSchema['comments_only'] ?? false);

    abort_unless((int)$termSubject->school_id === (int)$schoolId, 403);
    abort_unless(! $commentsOnly, 404);
    abort_unless((int)$termSubject->teacher_user_id === (int)$user->id, 403);

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;
    abort_unless($currentTerm && (int)$termSubject->term_id === (int)$currentTerm->id, 403);

    if ($school?->resultsArePublished()) {
      return response()->json([
        'message' => 'Results have been published. Staff can edit again after results are unpublished.',
      ], 423);
    }

    $rules = [
      'scores' => 'required|array|min:1',
      'scores.*.student_id' => 'required|integer',
    ];

    if ($commentsOnly) {
      $rules['scores.*.teacher_comment'] = 'nullable|string|max:1000';
    } else {
      $rules['scores.*.ca'] = 'nullable|numeric|decimal:0,1|min:0|max:100';
      $rules['scores.*.ca_breakdown'] = 'nullable|array|size:' . $caSlotCount;
      $rules['scores.*.ca_breakdown.*'] = 'nullable|numeric|decimal:0,1|min:0|max:100';
      $rules['scores.*.exam'] = 'required|numeric|decimal:0,1|min:0|max:100';
      if ($usesClassicComponents) {
        $rules['scores.*.classic_components'] = 'nullable|array';
        $rules['scores.*.classic_components.psychomotor'] = 'nullable|array|size:5';
        $rules['scores.*.classic_components.psychomotor.*'] = 'nullable|integer|min:0|max:100';
        $rules['scores.*.classic_components.quiz'] = 'nullable|integer|min:0|max:100';
        $rules['scores.*.classic_components.note_copying'] = 'nullable|integer|min:0|max:100';
      }
    }

    $payload = $request->validate($rules);

    // A result snapshot is immutable for grading shape. This keeps previously uploaded
    // Classic Full records intact when a school changes the template for new entries.
    $existingResultsByStudent = Result::query()
      ->where('school_id', $schoolId)
      ->where('term_subject_id', $termSubject->id)
      ->whereIn('student_id', collect($payload['scores'])->pluck('student_id')->map(fn ($id) => (int) $id)->all())
      ->get(['student_id', 'ca', 'ca_breakdown', 'exam', 'assessment_profile', 'classic_components'])
      ->keyBy('student_id');

    $eligibleStudentIdsQuery = Enrollment::query()
      ->where('enrollments.class_id', $termSubject->class_id)
      ->where('enrollments.term_id', $termSubject->term_id)
      ->select('enrollments.student_id');
    if (Schema::hasColumn('enrollments', 'school_id')) {
      $eligibleStudentIdsQuery->where('enrollments.school_id', $schoolId);
    }
    if ($canUseExclusions) {
      $eligibleStudentIdsQuery->leftJoin('student_subject_exclusions', function ($join) use ($schoolId, $termSubject, $session) {
        $join->on('student_subject_exclusions.student_id', '=', 'enrollments.student_id')
          ->where('student_subject_exclusions.school_id', '=', $schoolId)
          ->where('student_subject_exclusions.academic_session_id', '=', (int) $session->id)
          ->where('student_subject_exclusions.class_id', '=', (int) $termSubject->class_id)
          ->where('student_subject_exclusions.subject_id', '=', (int) $termSubject->subject_id);
      })
      ->whereNull('student_subject_exclusions.student_id');
    }
    $eligibleStudentIds = $eligibleStudentIdsQuery
      ->distinct()
      ->pluck('enrollments.student_id')
      ->map(fn ($id) => (int) $id)
      ->all();

    $preparedScores = [];
    $errors = [];

    foreach ($payload['scores'] as $idx => $score) {
      $studentId = (int) $score['student_id'];

      if (!in_array($studentId, $eligibleStudentIds, true)) {
        $errors["scores.$idx.student_id"] = [
          'Student is not eligible for this subject in the current term.',
        ];
      }

      if ($commentsOnly) {
        $preparedScores[] = [
          'student_id' => $studentId,
          'ca' => 0,
          'ca_breakdown' => array_fill(0, 5, 0),
          'exam' => 0,
          'teacher_comment' => trim((string) ($score['teacher_comment'] ?? '')),
          'classic_components' => null,
          'uses_classic_components' => false,
          'assessment_profile' => $assessmentSchema,
        ];
        continue;
      }

      $existingResult = $existingResultsByStudent->get($studentId);
      $rowSchema = $this->entrySchemaForResult($existingResult, $assessmentSchema);
      $rowCaMaxes = $rowSchema['ca_maxes'];
      $rowCaSlotCount = max(1, count($rowCaMaxes));
      $rowCaTotalMax = array_sum($rowCaMaxes);
      $rowExamMax = (float) $rowSchema['exam_max'];
      $rowUsesClassicComponents = AssessmentSchema::usesClassicComponents($rowSchema);
      $rawBreakdown = $score['ca_breakdown'] ?? null;
      $legacyCa = (float) ($score['ca'] ?? 0);

      if (is_array($rawBreakdown)) {
        for ($i = 0; $i < $rowCaSlotCount; $i++) {
          $inputVal = (float) ($rawBreakdown[$i] ?? 0);
          if ($inputVal > (float) $rowCaMaxes[$i]) {
            $errors["scores.$idx.ca_breakdown.$i"] = [
              'CA' . ($i + 1) . " score cannot be more than {$rowCaMaxes[$i]}.",
            ];
          }
        }
      } elseif ($legacyCa > $rowCaTotalMax) {
        $errors["scores.$idx.ca"] = [
          "CA score cannot be more than {$rowCaTotalMax}.",
        ];
      }

      $caBreakdown = AssessmentSchema::normalizeBreakdown($rawBreakdown, $rowSchema, $legacyCa);
      $caTotal = AssessmentSchema::breakdownTotal($caBreakdown);
      $exam = round((float) ($score['exam'] ?? 0), 1);
      if ($exam > $rowExamMax) {
        $errors["scores.$idx.exam"] = [
          "Exam score cannot be more than {$rowExamMax}.",
        ];
      }

      $classicComponents = $rowUsesClassicComponents
        ? ClassicResultComponents::forStorage($score['classic_components'] ?? ($existingResult?->classic_components ?? null), $rowSchema)
        : null;
      if (AssessmentSchema::totalFromScores($caBreakdown, $caTotal, $exam, $classicComponents, $rowSchema) > 100) {
        $errors["scores.$idx.total"] = ['The combined assessment score cannot be more than 100.'];
      }

      $preparedScores[] = [
        'student_id' => $studentId,
        'ca' => $caTotal,
        'ca_breakdown' => $caBreakdown,
        'exam' => $exam,
        'classic_components' => $classicComponents,
        'uses_classic_components' => $rowUsesClassicComponents,
        'assessment_profile' => $rowSchema,
      ];
    }

    if (!empty($errors)) {
      throw ValidationException::withMessages($errors);
    }

    $hasAssessmentProfile = $this->hasAssessmentProfileColumn();
    DB::transaction(function () use ($preparedScores, $termSubject, $schoolId, $commentsOnly, $hasTeacherComment, $hasClassicComponents, $hasAssessmentProfile) {
      $hasCaBreakdown = $this->hasResultCaBreakdownColumn();
      foreach ($preparedScores as $score) {
        $updateData = [
          'ca' => $score['ca'],
          'exam' => $score['exam'],
        ];
        if ($hasCaBreakdown) {
          $updateData['ca_breakdown'] = $score['ca_breakdown'];
        }
        if ($hasTeacherComment) {
          $updateData['teacher_comment'] = $commentsOnly ? $score['teacher_comment'] : null;
        }
        if ($hasClassicComponents) {
          $updateData['classic_components'] = $score['uses_classic_components'] ? $score['classic_components'] : null;
        }
        if ($hasAssessmentProfile) {
          $updateData['assessment_profile'] = $score['assessment_profile'];
        }

        Result::updateOrCreate(
          [
            'school_id' => $schoolId,
            'term_subject_id' => $termSubject->id,
            'student_id' => $score['student_id'],
          ],
          $updateData
        );
      }
    });

    return response()->json([
      'message' => $commentsOnly ? 'Teacher observations saved successfully' : 'Scores saved successfully',
    ]);
  }

  // GET /api/staff/results/comments-only/classes/{schoolClass}/students
  public function commentOnlyClassStudents(Request $request, SchoolClass $schoolClass)
  {
    $user = $request->user();
    $schoolId = (int) $user->school_id;
    $school = School::find($schoolId);
    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;

    abort_unless($session && $currentTerm, 422);
    abort_unless((int) $schoolClass->school_id === $schoolId, 403);
    abort_unless((int) $schoolClass->academic_session_id === (int) $session->id, 403);
    abort_unless((int) $schoolClass->class_teacher_user_id === (int) $user->id, 403);
    abort_unless(ClassTemplateSchema::commentsOnlyForClass($school?->class_templates, $schoolClass->level, $schoolClass->name), 404);

    $canUseDepartments = Schema::hasTable('class_departments') && Schema::hasColumn('enrollments', 'department_id');
    $studentsQuery = Enrollment::query()
      ->join('students', 'students.id', '=', 'enrollments.student_id')
      ->join('users', 'users.id', '=', 'students.user_id')
      ->where('enrollments.class_id', $schoolClass->id)
      ->where('enrollments.term_id', $currentTerm->id)
      ->where('students.school_id', $schoolId)
      ->select([
        'students.id as student_id',
        'users.name as student_name',
        ...($canUseDepartments ? ['class_departments.id as department_id', 'class_departments.name as department_name'] : [DB::raw('NULL as department_id'), DB::raw('NULL as department_name')]),
      ])
      ->orderBy('users.name');
    if ($canUseDepartments) {
      $studentsQuery->leftJoin('class_departments', 'class_departments.id', '=', 'enrollments.department_id');
    }
    if (Schema::hasColumn('enrollments', 'school_id')) {
      $studentsQuery->where('enrollments.school_id', $schoolId);
    }

    $observations = StudentBehaviourRating::query()
      ->where('school_id', $schoolId)
      ->where('class_id', $schoolClass->id)
      ->where('term_id', $currentTerm->id)
      ->get()
      ->keyBy('student_id');
    $commentOnlySchema = CommentOnlySchema::normalize($school?->comment_only_schema);

    $students = $studentsQuery->get()->map(function ($student) use ($observations, $commentOnlySchema) {
      $observation = $observations->get((int) $student->student_id);
      return [
        'student_id' => (int) $student->student_id,
        'student_name' => (string) $student->student_name,
        'department_id' => isset($student->department_id) ? (int) $student->department_id : null,
        'department_name' => (string) ($student->department_name ?? ''),
        'learning_observations' => CommentOnlySchema::observations($observation?->learning_observations, $commentOnlySchema),
      ];
    })->values();

    return response()->json(['data' => [
      'entry_type' => 'comments_only',
      'class_id' => (int) $schoolClass->id,
      'class_name' => (string) $schoolClass->name,
      'class_level' => (string) $schoolClass->level,
      'term_id' => (int) $currentTerm->id,
      'term_name' => (string) $currentTerm->name,
      'subject_name' => 'Assessment Sheet',
      'assessment_schema' => ['comments_only' => true],
      'comment_only_schema' => $commentOnlySchema,
      'results_published' => $school?->resultsArePublished() ?? false,
      'students' => $students,
    ]]);
  }

  // POST /api/staff/results/comments-only/classes/{schoolClass}/observations
  public function saveCommentOnlyClassObservations(Request $request, SchoolClass $schoolClass)
  {
    $user = $request->user();
    $schoolId = (int) $user->school_id;
    $school = School::find($schoolId);
    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;

    abort_unless($session && $currentTerm, 422);
    abort_unless((int) $schoolClass->school_id === $schoolId, 403);
    abort_unless((int) $schoolClass->academic_session_id === (int) $session->id, 403);
    abort_unless((int) $schoolClass->class_teacher_user_id === (int) $user->id, 403);
    abort_unless(ClassTemplateSchema::commentsOnlyForClass($school?->class_templates, $schoolClass->level, $schoolClass->name), 404);
    if ($school?->resultsArePublished()) {
      return response()->json(['message' => 'Results have been published. Staff can edit again after results are unpublished.'], 423);
    }

    $payload = $request->validate([
      'rows' => 'required|array|min:1',
      'rows.*.student_id' => 'required|integer',
      'rows.*.learning_observations' => 'nullable|array|max:' . CommentOnlySchema::MAX_CRITERIA_COUNT,
      'rows.*.learning_observations.*' => 'nullable|string|max:1000',
    ]);
    $validStudentIds = Enrollment::query()
      ->where('class_id', $schoolClass->id)
      ->where('term_id', $currentTerm->id)
      ->when(Schema::hasColumn('enrollments', 'school_id'), fn ($query) => $query->where('school_id', $schoolId))
      ->pluck('student_id')
      ->map(fn ($id) => (int) $id)
      ->all();
    $validSet = array_flip($validStudentIds);
    $commentOnlySchema = CommentOnlySchema::normalize($school?->comment_only_schema);

    DB::transaction(function () use ($payload, $schoolId, $schoolClass, $currentTerm, $user, $validSet, $commentOnlySchema) {
      foreach ($payload['rows'] as $row) {
        $studentId = (int) $row['student_id'];
        if (! isset($validSet[$studentId])) {
          continue;
        }
        StudentBehaviourRating::updateOrCreate(
          ['school_id' => $schoolId, 'class_id' => $schoolClass->id, 'term_id' => $currentTerm->id, 'student_id' => $studentId],
          [
            'learning_observations' => CommentOnlySchema::observations($row['learning_observations'] ?? [], $commentOnlySchema),
            'learning_area_schema' => $commentOnlySchema,
            'set_by_user_id' => $user->id,
          ]
        );
      }
    });

    return response()->json(['message' => 'Assessment observations saved successfully.']);
  }

  private function gradeFromTotal(int $schoolId, float $total): string
  {
    $schema = School::where('id', $schoolId)->value('grading_schema');
    return GradingSchema::gradeForTotal($schema, $total);
  }

  private function entrySchemaForResult(?object $result, array $currentSchema): array
  {
    // A zero-only row was created before staff began grading. It is safe to
    // adopt the school’s latest allocation, avoiding stale CA limits such as
    // “CA4 cannot be more than 0”. Rows containing actual entered scores keep
    // their snapshot so a later template change cannot erase or reinterpret it.
    if (!$this->resultHasEnteredScores($result)) {
      return AssessmentSchema::normalizeSchema($currentSchema);
    }

    return AssessmentSchema::schemaForResult($result?->assessment_profile ?? null, $currentSchema);
  }

  private function resultHasEnteredScores(?object $result): bool
  {
    if (!$result) {
      return false;
    }

    if ((float) ($result->ca ?? 0) > 0 || (float) ($result->exam ?? 0) > 0) {
      return true;
    }

    $breakdown = $result->ca_breakdown ?? [];
    if (is_string($breakdown)) {
      $decoded = json_decode($breakdown, true);
      $breakdown = is_array($decoded) ? $decoded : [];
    }
    if (is_array($breakdown) && collect($breakdown)->contains(fn ($value) => (float) $value > 0)) {
      return true;
    }

    $components = $result->classic_components ?? [];
    if (is_string($components)) {
      $decoded = json_decode($components, true);
      $components = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($components)) {
      return false;
    }

    // _initialized is written only after a staff member saves an editable
    // classic component, including a deliberate zero score.
    if (filter_var($components['_initialized'] ?? false, FILTER_VALIDATE_BOOL)) {
      return true;
    }

    $psychomotor = is_array($components['psychomotor'] ?? null) ? $components['psychomotor'] : [];
    return collect($psychomotor)->contains(fn ($value) => (float) $value > 0)
      || (float) ($components['quiz'] ?? 0) > 0
      || (float) ($components['note_copying'] ?? 0) > 0;
  }

  private function assessmentSchemaForTermSubject(?School $school, TermSubject $termSubject): array
  {
    $class = DB::table('classes')
      ->where('id', (int) $termSubject->class_id)
      ->where('school_id', (int) $termSubject->school_id)
      ->first(['level', 'name']);

    return AssessmentSchema::schemaForTemplateClass(
      $school?->assessment_schema,
      $class->level ?? null,
      $class->name ?? null,
      $school?->result_template_config,
      $school?->class_templates
    );
  }
}

