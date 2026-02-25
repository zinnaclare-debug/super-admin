<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\LevelDepartment;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $selectedLevels = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->pluck('level')
                ->map(fn ($level) => strtolower(trim((string) $level)))
                ->filter(fn ($level) => $level !== '')
                ->unique()
                ->values()
                ->all();
        }

        $levels = [];
        foreach ($selectedLevels as $level) {
            $levels[] = [
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
            $allowedLevels = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
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

            $classes = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('level', $requestedLevel)
                ->get(['id']);

            foreach ($classes as $classRow) {
                ClassDepartment::firstOrCreate([
                    'school_id' => $schoolId,
                    'class_id' => $classRow->id,
                    'name' => $department->name,
                ]);
            }

            return response()->json(['data' => $department], 201);
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
}

