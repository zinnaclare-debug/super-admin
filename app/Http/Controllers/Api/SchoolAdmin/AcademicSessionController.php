<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\TermSubject;
use App\Models\LevelDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class AcademicSessionController extends Controller
{
    /**
     * GET /api/school-admin/academic-sessions
     * List all academic sessions for the school
     */
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $sessions = AcademicSession::where('school_id', $schoolId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * POST /api/school-admin/academic-sessions
     * Create a new academic session
     */
public function store(Request $request)
{
    $schoolId = $request->user()->school_id;

    $data = $request->validate([
        'session_name'  => 'required|string|max:50', // e.g. 2025/2026
        'academic_year' => 'nullable|string|max:20',
        'levels'        => 'required|array|min:1',
        'levels.*'      => 'in:nursery,primary,secondary',
        'class_structure' => 'nullable|array',
        'class_structure.nursery' => 'sometimes|array',
        'class_structure.nursery.*' => 'nullable|string|max:50',
        'class_structure.primary' => 'sometimes|array',
        'class_structure.primary.*' => 'nullable|string|max:50',
        'class_structure.secondary' => 'sometimes|array',
        'class_structure.secondary.*' => 'nullable|string|max:50',
    ]);

    return DB::transaction(function () use ($schoolId, $data) {

        $session = AcademicSession::create([
            'school_id'     => $schoolId,
            'session_name'  => $data['session_name'],
            'academic_year' => $data['academic_year'] ?? $data['session_name'],
            // Session lifecycle is controlled by super admin.
            'status'        => 'pending',
            'levels'        => array_values($data['levels']), // ✅ store selected only
        ]);

        // ✅ Auto-create 3 terms
        $termNames = ['First Term', 'Second Term', 'Third Term'];
        foreach ($termNames as $idx => $name) {
            Term::firstOrCreate([
                'school_id' => $schoolId,
                'academic_session_id' => $session->id,
                'name' => $name,
            ], [
                'is_current' => false,
            ]);
        }

        // ✅ Auto-create classes ONLY for selected levels
        $defaultClassMap = [
            'nursery' => ['Nursery 1', 'Nursery 2', 'Nursery 3'],
            'primary' => ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'],
            'secondary' => ['JS1 (Grade 7)', 'JS2 (Grade 8)', 'JS3 (Grade 9)', 'SS1 (Grade 10)', 'SS2 (Grade 11)', 'SS3 (Grade 12)'],
        ];

        $customClassMap = collect($data['class_structure'] ?? [])
            ->map(function ($classNames) {
                if (!is_array($classNames)) {
                    return [];
                }

                return collect($classNames)
                    ->map(fn ($name) => trim((string) $name))
                    ->filter(fn ($name) => $name !== '')
                    ->unique()
                    ->values()
                    ->all();
            })
            ->all();

        foreach ($data['levels'] as $lvl) {
            $classNames = $customClassMap[$lvl] ?? $defaultClassMap[$lvl];
            if (empty($classNames)) {
                $classNames = $defaultClassMap[$lvl];
            }

            foreach ($classNames as $className) {
                SchoolClass::firstOrCreate([
                    'school_id' => $schoolId,
                    'academic_session_id' => $session->id,
                    'level' => $lvl,
                    'name' => $className,
                ]);
            }
        }

        $this->copyPreviousSessionSubjectMappings($schoolId, (int) $session->id);

        return response()->json(['data' => $session], 201);
    });
}


    /**
     * PUT /api/school-admin/academic-sessions/{session}
     * Update an academic session
     */
    public function update(Request $request, AcademicSession $session)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($session->school_id === $schoolId, 403);

        $payload = $request->validate([
            'session_name' => 'required|string|max:50',
            'academic_year' => 'nullable|string|max:50',
            'levels' => 'nullable|array',
            'levels.*' => 'in:nursery,primary,secondary',
        ]);

        $session->update([
            'session_name' => $payload['session_name'],
            'academic_year' => $payload['academic_year'] ?? null,
            'levels' => $payload['levels'] ?? $session->levels,
        ]);

        return response()->json(['data' => $session]);
    }

    /**
     * DELETE /api/school-admin/academic-sessions/{session}
     * Delete an academic session
     */
    public function destroy(Request $request, AcademicSession $session)
    {
        return response()->json([
            'message' => 'Only super admin can delete academic sessions.',
        ], 403);
    }

    /**
     * PATCH /api/school-admin/academic-sessions/{session}/status
     * Change session status (current/completed)
     */
    public function setStatus(Request $request, AcademicSession $session)
    {
        return response()->json([
            'message' => 'Only super admin can change academic session status.',
        ], 403);
    }

    /**
     * GET /api/school-admin/academic-sessions/{session}/details
     * Returns session + ONLY the levels selected/stored in $session->levels
     */
    public function details(Request $request, AcademicSession $session)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($session->school_id === $schoolId, 403);

        // ✅ levels stored in DB (JSON/array)
        // Accept either:
        // 1) ["nursery","primary"]
        // 2) [{"level":"nursery"},{"level":"primary"}]
        $rawLevels = $session->levels ?? [];

        // Normalize -> ["nursery","primary",...]
        $levels = collect($rawLevels)->map(function ($item) {
            if (is_array($item)) {
                return $item['level'] ?? null;
            }
            return $item; // string
        })->filter()->values()->all();

        // Optional safety: allow only known levels (prevents bad data)
        $allowed = ['nursery', 'primary', 'secondary'];
        $levels = array_values(array_filter($levels, fn ($lvl) => in_array($lvl, $allowed, true)));

        $data = [];

        foreach ($levels as $lvl) {
            $data[] = [
                'level' => $lvl,

                // ✅ classes for this level + session
                'classes' => SchoolClass::where('school_id', $schoolId)
                    ->where('academic_session_id', $session->id)
                    ->where('level', $lvl)
                    ->orderBy('id')
                    ->get(),

                // ✅ departments for this level + session
                'departments' => LevelDepartment::where('school_id', $schoolId)
                    ->where('academic_session_id', $session->id)
                    ->where('level', $lvl)
                    ->orderBy('name')
                    ->get(),
            ];
        }

        $terms = Term::where('school_id', $schoolId)
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
            ]
        ]);
    }

    /**
     * PATCH /api/school-admin/terms/{term}/set-current
     */
    public function setCurrentTerm(Request $request, Term $term)
    {
        $schoolId = $request->user()->school_id;
        abort_unless((int)$term->school_id === (int)$schoolId, 403);

        $session = AcademicSession::where('id', $term->academic_session_id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$session || $session->status !== 'current') {
            return response()->json(['message' => 'Current term can only be set for the current academic session'], 422);
        }

        Term::where('school_id', $schoolId)
            ->where('academic_session_id', $term->academic_session_id)
            ->update(['is_current' => false]);

        $term->update(['is_current' => true]);

        return response()->json(['data' => $term]);
    }

    /**
     * POST /api/school-admin/academic-sessions/{session}/level-departments
     * Create a department ONLY inside the session's allowed levels.
     */
    public function createLevelDepartment(Request $request, AcademicSession $session)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($session->school_id === $schoolId, 403);

        $payload = $request->validate([
            'level' => 'required|string',
            'name' => 'required|string|max:50',
        ]);

        // ✅ Validate requested level exists in session->levels
        $rawLevels = $session->levels ?? [];
        $allowedLevels = collect($rawLevels)->map(function ($item) {
            return is_array($item) ? ($item['level'] ?? null) : $item;
        })->filter()->values()->all();

        abort_unless(in_array($payload['level'], $allowedLevels, true), 403);

        // optional uniqueness per session+level
        $exists = LevelDepartment::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('level', $payload['level'])
            ->where('name', $payload['name'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Department already exists'], 409);
        }

        $dep = LevelDepartment::create([
            'school_id' => $schoolId,
            'academic_session_id' => $session->id,
            'level' => $payload['level'],
            'name' => $payload['name'],
        ]);

        return response()->json(['data' => $dep], 201);
    }

    /**
     * GET /api/school-admin/classes/{class}/terms
     */
    public function classTerms(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $terms = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'class' => $class,
                'terms' => $terms,
            ]
        ]);
    }

    /**
     * PUT /api/school-admin/terms/{term}
     */
    public function updateTerm(Request $request, Term $term)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($term->school_id === $schoolId, 403);

        $payload = $request->validate([
            'name' => 'required|string|max:50'
        ]);

        $term->update(['name' => $payload['name']]);

        return response()->json(['data' => $term]);
    }

    /**
     * DELETE /api/school-admin/terms/{term}
     */
    public function deleteTerm(Request $request, Term $term)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($term->school_id === $schoolId, 403);

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

        if (! $previousSession) {
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
            if (! $previousClass) {
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
                if (! $oldTermName) {
                    continue;
                }

                $newTerm = $newTermByName->get($oldTermName);
                if (! $newTerm) {
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
