<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\LevelDepartment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\TermSubject;
use App\Support\ClassTemplateSchema;
use App\Support\DepartmentTemplateSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicSessionController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;

        $sessions = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    public function store(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;

        $data = $request->validate([
            'session_name' => 'required|string|max:50',
            'academic_year' => 'nullable|string|max:20',
        ]);

        return DB::transaction(function () use ($schoolId, $data) {
            $school = School::query()->find($schoolId);
            if (!$school) {
                return response()->json(['message' => 'School not found.'], 404);
            }

            $classTemplates = ClassTemplateSchema::normalize($school->class_templates);
            $activeSections = ClassTemplateSchema::activeSections($classTemplates);
            if (empty($activeSections)) {
                return response()->json([
                    'message' => 'No class template configured. Ask Super Admin to set class templates in School Information first.',
                ], 422);
            }

            $activeLevels = array_values(array_map(
                fn (array $section) => strtolower(trim((string) ($section['key'] ?? ''))),
                $activeSections
            ));

            $session = AcademicSession::create([
                'school_id' => $schoolId,
                'session_name' => $data['session_name'],
                'academic_year' => $data['academic_year'] ?? $data['session_name'],
                'status' => 'pending',
                'levels' => $activeLevels,
            ]);

            foreach (['First Term', 'Second Term', 'Third Term'] as $name) {
                Term::firstOrCreate([
                    'school_id' => $schoolId,
                    'academic_session_id' => $session->id,
                    'name' => $name,
                ], [
                    'is_current' => false,
                ]);
            }

            foreach ($activeSections as $section) {
                $level = strtolower(trim((string) ($section['key'] ?? '')));
                $classNames = ClassTemplateSchema::activeClassNames($section);

                foreach ($classNames as $className) {
                    SchoolClass::firstOrCreate([
                        'school_id' => $schoolId,
                        'academic_session_id' => $session->id,
                        'level' => $level,
                        'name' => $className,
                    ]);
                }
            }

            $departmentTemplates = DepartmentTemplateSync::normalizeTemplateNames(
                $school->department_templates ?? []
            );
            DepartmentTemplateSync::syncTemplatesToSession(
                $schoolId,
                (int) $session->id,
                $activeLevels,
                $departmentTemplates
            );

            $this->copyPreviousSessionSubjectMappings($schoolId, (int) $session->id);

            return response()->json(['data' => $session], 201);
        });
    }

    public function update(Request $request, AcademicSession $session)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $session->school_id === $schoolId, 403);

        $payload = $request->validate([
            'session_name' => 'required|string|max:50',
            'academic_year' => 'nullable|string|max:50',
        ]);

        $session->update([
            'session_name' => $payload['session_name'],
            'academic_year' => $payload['academic_year'] ?? null,
        ]);

        return response()->json(['data' => $session]);
    }

    public function destroy(Request $request, AcademicSession $session)
    {
        return response()->json([
            'message' => 'Only super admin can delete academic sessions.',
        ], 403);
    }

    public function setStatus(Request $request, AcademicSession $session)
    {
        return response()->json([
            'message' => 'Only super admin can change academic session status.',
        ], 403);
    }

    public function details(Request $request, AcademicSession $session)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $session->school_id === $schoolId, 403);

        $levels = collect((array) ($session->levels ?? []))
            ->map(function ($item) {
                $value = is_array($item) ? ($item['level'] ?? null) : $item;
                return strtolower(trim((string) $value));
            })
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($levels)) {
            $levels = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->orderBy('id')
                ->pluck('level')
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter(fn ($value) => $value !== '')
                ->unique()
                ->values()
                ->all();
        }

        $data = [];
        foreach ($levels as $level) {
            $data[] = [
                'level' => $level,
                'classes' => SchoolClass::query()
                    ->where('school_id', $schoolId)
                    ->where('academic_session_id', $session->id)
                    ->where('level', $level)
                    ->orderBy('id')
                    ->get(),
                'departments' => LevelDepartment::query()
                    ->where('school_id', $schoolId)
                    ->where('academic_session_id', $session->id)
                    ->where('level', $level)
                    ->orderBy('name')
                    ->get(),
            ];
        }

        $terms = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'name', 'is_current']);
        $currentTerm = $terms->firstWhere('is_current', true);

        return response()->json([
            'data' => [
                'session' => $session,
                'terms' => $terms,
                'current_term' => $currentTerm,
                'levels' => $data,
            ],
        ]);
    }

    public function setCurrentTerm(Request $request, Term $term)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $term->school_id === $schoolId, 403);

        $session = AcademicSession::query()
            ->where('id', $term->academic_session_id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$session || $session->status !== 'current') {
            return response()->json([
                'message' => 'Current term can only be set for the current academic session',
            ], 422);
        }

        Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $term->academic_session_id)
            ->update(['is_current' => false]);

        $term->update(['is_current' => true]);

        return response()->json(['data' => $term]);
    }

    public function createLevelDepartment(Request $request, AcademicSession $session)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $session->school_id === $schoolId, 403);

        $payload = $request->validate([
            'level' => 'required|string|max:60',
            'name' => 'required|string|max:50',
        ]);

        $requestedLevel = strtolower(trim((string) $payload['level']));

        $allowedLevels = collect((array) ($session->levels ?? []))
            ->map(function ($item) {
                $value = is_array($item) ? ($item['level'] ?? null) : $item;
                return strtolower(trim((string) $value));
            })
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($allowedLevels)) {
            $allowedLevels = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->pluck('level')
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter(fn ($value) => $value !== '')
                ->unique()
                ->values()
                ->all();
        }

        abort_unless(in_array($requestedLevel, $allowedLevels, true), 403);

        $exists = LevelDepartment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('level', $requestedLevel)
            ->where('name', trim((string) $payload['name']))
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Department already exists'], 409);
        }

        $department = LevelDepartment::create([
            'school_id' => $schoolId,
            'academic_session_id' => $session->id,
            'level' => $requestedLevel,
            'name' => trim((string) $payload['name']),
        ]);

        return response()->json(['data' => $department], 201);
    }

    public function classTerms(Request $request, SchoolClass $class)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $class->school_id === $schoolId, 403);

        $terms = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'class' => $class,
                'terms' => $terms,
            ],
        ]);
    }

    public function updateTerm(Request $request, Term $term)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $term->school_id === $schoolId, 403);

        $payload = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $term->update(['name' => $payload['name']]);

        return response()->json(['data' => $term]);
    }

    public function deleteTerm(Request $request, Term $term)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $term->school_id === $schoolId, 403);

        $term->delete();

        return response()->json(['message' => 'Term deleted']);
    }

    private function copyPreviousSessionSubjectMappings(int $schoolId, int $newSessionId): void
    {
        $previousSession = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('id', '!=', $newSessionId)
            ->orderByDesc('created_at')
            ->first();

        if (!$previousSession) {
            return;
        }

        $previousTerms = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $previousSession->id)
            ->get(['id', 'name']);
        $newTerms = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $newSessionId)
            ->get(['id', 'name']);

        if ($previousTerms->isEmpty() || $newTerms->isEmpty()) {
            return;
        }

        $previousTermNameById = $previousTerms->mapWithKeys(
            fn ($term) => [(int) $term->id => strtolower(trim((string) $term->name))]
        );
        $newTermByName = $newTerms->keyBy(fn ($term) => strtolower(trim((string) $term->name)));

        $previousClasses = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $previousSession->id)
            ->get(['id', 'level', 'name'])
            ->keyBy(fn ($class) => strtolower((string) $class->level) . '|' . strtolower(trim((string) $class->name)));
        $newClasses = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $newSessionId)
            ->get(['id', 'level', 'name']);

        if ($previousClasses->isEmpty() || $newClasses->isEmpty()) {
            return;
        }

        $hasSchoolId = Schema::hasColumn('term_subjects', 'school_id');
        $hasTeacherUserId = Schema::hasColumn('term_subjects', 'teacher_user_id');

        foreach ($newClasses as $newClass) {
            $classKey = strtolower((string) $newClass->level) . '|' . strtolower(trim((string) $newClass->name));
            $previousClass = $previousClasses->get($classKey);
            if (!$previousClass) {
                continue;
            }

            $assignmentQuery = TermSubject::query()
                ->where('class_id', $previousClass->id)
                ->whereIn('term_id', $previousTerms->pluck('id'));
            if ($hasSchoolId) {
                $assignmentQuery->where('school_id', $schoolId);
            }

            $assignments = $assignmentQuery->get();
            if ($assignments->isEmpty()) {
                continue;
            }

            foreach ($assignments as $assignment) {
                $oldTermName = $previousTermNameById->get((int) $assignment->term_id);
                if (!$oldTermName) {
                    continue;
                }

                $newTerm = $newTermByName->get($oldTermName);
                if (!$newTerm) {
                    continue;
                }

                $where = [
                    'class_id' => (int) $newClass->id,
                    'term_id' => (int) $newTerm->id,
                    'subject_id' => (int) $assignment->subject_id,
                ];
                if ($hasSchoolId) {
                    $where['school_id'] = $schoolId;
                }

                $values = [];
                if ($hasTeacherUserId) {
                    $values['teacher_user_id'] = $assignment->teacher_user_id;
                }

                TermSubject::updateOrCreate($where, $values);
            }
        }
    }
}
