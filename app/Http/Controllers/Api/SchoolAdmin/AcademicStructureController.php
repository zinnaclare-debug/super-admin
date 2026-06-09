<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\LevelDepartment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\SchoolSubscriptionBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicStructureController extends Controller
{
    public function details(Request $request, AcademicSession $session)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $session->school_id === $schoolId, 403);

        $selectedLevels = collect((array) ($session->levels ?? []))
            ->map(function ($item) {
                $value = is_array($item) ? ($item['level'] ?? null) : $item;
                return strtolower(trim((string) $value));
            })
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($selectedLevels)) {
            $selectedLevelsQuery = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id);

            $selectedLevels = $this->onlyTemplateActive($selectedLevelsQuery, 'classes')
                ->pluck('level')
                ->map(fn ($level) => strtolower(trim((string) $level)))
                ->filter(fn ($level) => $level !== '')
                ->unique()
                ->values()
                ->all();
        }

        $levels = [];
        foreach ($selectedLevels as $level) {
            $classesQuery = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('level', $level);
            $departmentsQuery = LevelDepartment::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('level', $level);

            $levels[] = [
                'level' => $level,
                'classes' => $this->onlyTemplateActive($classesQuery, 'classes')
                    ->orderBy('id')
                    ->get(),
                'departments' => $this->onlyTemplateActive($departmentsQuery, 'level_departments')
                    ->orderBy('name')
                    ->get(),
            ];
        }

        return response()->json([
            'data' => [
                'session' => $session,
                'terms' => Term::query()
                    ->where('school_id', $schoolId)
                    ->where('academic_session_id', $session->id)
                    ->orderBy('id')
                    ->get(['id', 'name', 'is_current']),
                'current_term' => Term::query()
                    ->where('school_id', $schoolId)
                    ->where('academic_session_id', $session->id)
                    ->where('is_current', true)
                    ->first(['id', 'name', 'is_current']),
                'levels' => $levels,
            ],
        ]);
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
            $allowedLevelsQuery = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id);

            $allowedLevels = $this->onlyTemplateActive($allowedLevelsQuery, 'classes')
                ->pluck('level')
                ->map(fn ($level) => strtolower(trim((string) $level)))
                ->filter(fn ($level) => $level !== '')
                ->unique()
                ->values()
                ->all();
        }
        abort_unless(in_array($requestedLevel, $allowedLevels, true), 422);

        return DB::transaction(function () use ($schoolId, $session, $requestedLevel, $payload) {
            $department = LevelDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'academic_session_id' => $session->id,
                'level' => $requestedLevel,
                'name' => trim((string) $payload['name']),
            ]);
            $this->setModelTemplateActive($department, 'level_departments', true);
            $department->save();

            $classesQuery = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('level', $requestedLevel);

            $classes = $this->onlyTemplateActive($classesQuery, 'classes')
                ->get(['id']);

            foreach ($classes as $classRow) {
                $classDepartment = ClassDepartment::firstOrCreate([
                    'school_id' => $schoolId,
                    'class_id' => $classRow->id,
                    'name' => $department->name,
                ]);
                $this->setModelTemplateActive($classDepartment, 'class_departments', true);
                $classDepartment->save();
            }

            return response()->json(['data' => $department], 201);
        });
    }

    public function updateLevelDepartment(Request $request, AcademicSession $session, LevelDepartment $department)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $session->school_id === $schoolId, 403);
        abort_unless((int) $department->school_id === $schoolId, 403);
        abort_unless((int) $department->academic_session_id === (int) $session->id, 404);

        $payload = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $newName = trim((string) ($payload['name'] ?? ''));
        if ($newName === '') {
            return response()->json(['message' => 'Department name is required.'], 422);
        }

        $oldName = trim((string) $department->name);
        if (strcasecmp($oldName, $newName) === 0) {
            $department->name = $newName;
            $department->save();

            return response()->json(['data' => $department]);
        }

        $alreadyExists = LevelDepartment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->where('level', (string) $department->level)
            ->whereRaw('LOWER(name) = ?', [strtolower($newName)])
            ->where('id', '!=', (int) $department->id)
            ->exists();
        if ($alreadyExists) {
            return response()->json([
                'message' => 'A department with this name already exists for this level.',
            ], 409);
        }

        return DB::transaction(function () use ($schoolId, $session, $department, $oldName, $newName) {
            $department->name = $newName;
            $department->save();

            $classesQuery = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $session->id)
                ->where('level', (string) $department->level);

            $classes = $this->onlyTemplateActive($classesQuery, 'classes')
                ->get(['id']);

            foreach ($classes as $classRow) {
                $targetDepartmentId = ClassDepartment::query()
                    ->where('school_id', $schoolId)
                    ->where('class_id', (int) $classRow->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($newName)])
                    ->value('id');

                $sourceRows = ClassDepartment::query()
                    ->where('school_id', $schoolId)
                    ->where('class_id', (int) $classRow->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($oldName)])
                    ->orderBy('id')
                    ->get(['id']);

                if ($sourceRows->isEmpty()) {
                    continue;
                }

                $sourceIds = $sourceRows->pluck('id')->map(fn ($id) => (int) $id)->values();
                $keeperId = $targetDepartmentId ? (int) $targetDepartmentId : (int) $sourceIds->first();

                if (!$targetDepartmentId) {
                    ClassDepartment::query()
                        ->where('id', $keeperId)
                        ->update(['name' => $newName]);
                }

                $idsToDelete = $sourceIds->filter(fn ($id) => $id !== $keeperId)->values();
                if ($idsToDelete->isEmpty()) {
                    continue;
                }

                if (Schema::hasTable('enrollments')) {
                    DB::table('enrollments')
                        ->whereIn('department_id', $idsToDelete->all())
                        ->update(['department_id' => $keeperId]);
                }

                ClassDepartment::query()
                    ->whereIn('id', $idsToDelete->all())
                    ->delete();
            }

            return response()->json(['data' => $department]);
        });
    }

    public function deleteLevelDepartment(Request $request, AcademicSession $session, LevelDepartment $department)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $session->school_id === $schoolId, 403);
        abort_unless((int) $department->school_id === $schoolId, 403);
        abort_unless((int) $department->academic_session_id === (int) $session->id, 404);

        return DB::transaction(function () use ($schoolId, $session, $department) {
            $level = strtolower(trim((string) $department->level));
            $name = trim((string) $department->name);

            $department->delete();

            $classIdsQuery = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $session->id)
                ->where('level', $level);

            $classIds = $this->onlyTemplateActive($classIdsQuery, 'classes')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($classIds->isEmpty()) {
                return response()->json([
                    'message' => 'Department deleted.',
                    'meta' => [
                        'removed_class_departments' => 0,
                        'retained_class_departments' => 0,
                    ],
                ]);
            }

            $classDepartments = ClassDepartment::query()
                ->where('school_id', $schoolId)
                ->whereIn('class_id', $classIds->all())
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->get(['id']);

            $departmentIds = $classDepartments
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($departmentIds->isEmpty()) {
                return response()->json([
                    'message' => 'Department deleted.',
                    'meta' => [
                        'removed_class_departments' => 0,
                        'retained_class_departments' => 0,
                    ],
                ]);
            }

            $inUseIds = collect();
            if (Schema::hasTable('enrollments')) {
                $inUseIds = DB::table('enrollments')
                    ->whereIn('department_id', $departmentIds->all())
                    ->distinct()
                    ->pluck('department_id')
                    ->map(fn ($id) => (int) $id)
                    ->values();
            }

            $idsToDelete = $departmentIds
                ->reject(fn ($id) => $inUseIds->contains($id))
                ->values();

            $removed = 0;
            if ($idsToDelete->isNotEmpty()) {
                $removed = ClassDepartment::query()
                    ->whereIn('id', $idsToDelete->all())
                    ->delete();
            }

            return response()->json([
                'message' => 'Department deleted.',
                'meta' => [
                    'removed_class_departments' => (int) $removed,
                    'retained_class_departments' => (int) $inUseIds->count(),
                ],
            ]);
        });
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

    private function onlyTemplateActive($query, string $table)
    {
        if (Schema::hasColumn($table, 'is_template_active')) {
            $query->where($table . '.is_template_active', true);
        }

        return $query;
    }

    private function setModelTemplateActive(object $model, string $table, bool $active): void
    {
        if (Schema::hasColumn($table, 'is_template_active')) {
            $model->is_template_active = $active;
        }
    }

    public function setCurrentTerm(Request $request, Term $term)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $term->school_id === $schoolId, 403);

        $this->validateCurrentSelectionCode($request);

        $session = AcademicSession::query()
            ->where('id', $term->academic_session_id)
            ->where('school_id', $schoolId)
            ->first();

        if (!$session || $session->status !== 'current') {
            return response()->json([
                'message' => 'Current term can only be set for the current academic session',
            ], 422);
        }

        return DB::transaction(function () use ($schoolId, $term) {
            Term::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $term->academic_session_id)
                ->update(['is_current' => false]);

            $term->update(['is_current' => true]);

            $school = School::query()->find($schoolId);
            if ($school) {
                $this->resetLifecycleState($school);
            }

            return response()->json([
                'message' => 'Current term updated successfully. Results were unpublished for the new cycle.',
                'data' => $term->fresh(),
                'results_published' => false,
            ]);
        });
    }

    private function validateCurrentSelectionCode(Request $request): void
    {
        $validated = $request->validate([
            'current_selection_code' => ['required', 'digits:4'],
        ]);

        if (! hash_equals('2026', (string) $validated['current_selection_code'])) {
            abort(response()->json([
                'message' => 'Invalid current selection confirmation code.',
                'errors' => [
                    'current_selection_code' => ['Invalid current selection confirmation code.'],
                ],
            ], 422));
        }
    }

    private function resetLifecycleState(School $school): void
    {
        if ($school->results_published) {
            $school->results_published = false;
            $school->save();
        }

        $settings = SchoolSubscriptionBilling::getSettings($school);
        SchoolSubscriptionBilling::clearPendingOverride($settings);
    }
}
