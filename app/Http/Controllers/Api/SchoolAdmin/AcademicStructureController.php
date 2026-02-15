<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\LevelDepartment;
use App\Models\ClassDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicStructureController extends Controller
{
// GET /api/school-admin/academic-sessions/{session}/details
public function details(Request $request, AcademicSession $session)
{
    $schoolId = $request->user()->school_id;
    abort_unless($session->school_id === $schoolId, 403);

    /**
     * We expect $session->levels to be saved from creation.
     * It may look like:
     * [
     *   { "level": "Secondary", "classes": [...] },
     *   { "level": "Primary", "classes": [...] }
     * ]
     */
    $rawLevels = $session->levels;

    // ✅ If nothing saved (older data), fallback to all
    if (!is_array($rawLevels) || count($rawLevels) === 0) {
        $selectedLevels = ['nursery', 'primary', 'secondary'];
    } else {
        $selectedLevels = collect($rawLevels)
            ->map(function ($l) {
                // allow either "secondary" OR {level:"Secondary"}
                $name = is_array($l) ? ($l['level'] ?? '') : $l;
                $name = strtolower(trim($name));
                return match ($name) {
                    'nursery' => 'nursery',
                    'primary' => 'primary',
                    'secondary' => 'secondary',
                    default => null,
                };
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // just in case
        if (count($selectedLevels) === 0) {
            $selectedLevels = ['nursery', 'primary', 'secondary'];
        }
    }

    $data = [];
    foreach ($selectedLevels as $lvl) {
        $data[] = [
            'level' => $lvl,
            'classes' => SchoolClass::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('level', $lvl)
                ->orderBy('id')
                ->get(),
            'departments' => LevelDepartment::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('level', $lvl)
                ->orderBy('name')
                ->get(),
        ];
    }

    return response()->json([
        'data' => [
            'session' => $session,
            'terms' => Term::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->orderBy('id')
                ->get(['id', 'name', 'is_current']),
            'current_term' => Term::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('is_current', true)
                ->first(['id', 'name', 'is_current']),
            'levels'  => $data,
        ]
    ]);
}


    // POST /api/school-admin/academic-sessions/{session}/level-departments
    public function createLevelDepartment(Request $request, AcademicSession $session)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($session->school_id === $schoolId, 403);

        $payload = $request->validate([
            'level' => 'required|in:nursery,primary,secondary',
            'name' => 'required|string|max:50',
        ]);

        $rawLevels = $session->levels ?? [];
        $allowedLevels = collect($rawLevels)
            ->map(function ($item) {
                return is_array($item) ? ($item['level'] ?? null) : $item;
            })
            ->filter()
            ->map(fn ($level) => strtolower(trim((string) $level)))
            ->values()
            ->all();
        if (count($allowedLevels) === 0) {
            $allowedLevels = ['nursery', 'primary', 'secondary'];
        }
        abort_unless(in_array($payload['level'], $allowedLevels, true), 422);

        return DB::transaction(function () use ($schoolId, $session, $payload) {
            $dep = LevelDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'academic_session_id' => $session->id,
                'level' => $payload['level'],
                'name' => trim($payload['name']),
            ]);

            $classes = SchoolClass::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('level', $payload['level'])
                ->get(['id']);

            foreach ($classes as $classRow) {
                ClassDepartment::firstOrCreate([
                    'school_id' => $schoolId,
                    'class_id' => $classRow->id,
                    'name' => $dep->name,
                ]);
            }

            return response()->json(['data' => $dep], 201);
        });
    }

    // GET /api/school-admin/classes/{class}/terms
    public function classTerms(Request $request, SchoolClass $class)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($class->school_id === $schoolId, 403);

        $terms = Term::where('school_id',$schoolId)
            ->where('academic_session_id',$class->academic_session_id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'class' => $class,
                'terms' => $terms,
            ]
        ]);
    }

    // (Optional) PUT /api/school-admin/terms/{term}
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

    // (Optional) DELETE /api/school-admin/terms/{term}
    public function deleteTerm(Request $request, Term $term)
    {
        $schoolId = $request->user()->school_id;
        abort_unless($term->school_id === $schoolId, 403);

        // NOTE: deleting a term can break data later; keep if you really want it
        $term->delete();

        return response()->json(['message' => 'Term deleted']);
    }

    // PATCH /api/school-admin/terms/{term}/set-current
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
}
