<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SchoolFeatureController extends Controller
{
    // 1️⃣ READ FEATURES — return canonical feature list (no duplicates) with labels and categories
    public function index(?School $school = null)
    {
        // resolve school for school-admin requests
        if (! $school) {
            $school = auth()->user()?->school;
        }

        if (! $school) {
            return response()->json(['data' => []]);
        }

        $defs = config('features.definitions');
        $legacy = config('features.legacy_map', []);

        // Consolidate legacy feature rows into canonical keys (one-time cleanup behavior)
        foreach ($legacy as $old => $canonical) {
            $rows = $school->features()->where('feature', $old)->get();
            if ($rows->count() === 0) continue;

            // if canonical exists, merge enabled (canonical.enabled || any legacy enabled) then delete legacy rows
            $canonicalRow = $school->features()->where('feature', $canonical)->first();

            if ($canonicalRow) {
                $anyEnabled = $rows->contains(fn($r) => $r->enabled);
                $canonicalRow->enabled = $canonicalRow->enabled || $anyEnabled;
                $canonicalRow->save();
                foreach ($rows as $r) {
                    if ($r->id !== $canonicalRow->id) $r->delete();
                }
            } else {
                // rename the first legacy row to canonical and delete others
                $first = $rows->first();
                $first->feature = $canonical;
                $first->save();
                foreach ($rows->slice(1) as $r) {
                    $r->delete();
                }
            }
        }

        $data = [];

        foreach ($defs as $def) {
            $rec = $school->features()->where('feature', $def['key'])->first();

            if (! $rec) {
                $defaultEnabled = false;
                if (($def['key'] ?? '') === 'student_result') {
                    $studentReport = $school->features()->where('feature', 'student_report')->first();
                    $defaultEnabled = (bool) ($studentReport?->enabled);
                }

                $rec = SchoolFeature::create([
                    'school_id' => $school->id,
                    'feature' => $def['key'],
                    'enabled' => $defaultEnabled,
                ]);
            }

            $data[] = [
                'id' => $rec->id,
                'feature' => $def['key'],
                'label' => $def['label'] ?? $def['key'],
                'category' => $def['category'] ?? null,
                'enabled' => (bool) $rec->enabled,
                'description' => $rec->description ?? null,
            ];
        }

        return response()->json(['data' => $data]);
    }

    // 2️⃣ TOGGLE FEATURE — accepts legacy or canonical keys and school-admin requests
    public function toggle(Request $request, ?School $school = null)
    {
        $request->validate([
            'feature' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        if (! $school) {
            $school = auth()->user()?->school;
        }

        if (! $school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $legacy = config('features.legacy_map', []);
        $featureKey = $request->feature;

        if (array_key_exists($featureKey, $legacy)) {
            $featureKey = $legacy[$featureKey];
        }

        $rec = $school->features()->updateOrCreate([
            'feature' => $featureKey,
        ], [
            'enabled' => $request->enabled,
        ]);

        return response()->json([
            'message' => 'Feature updated',
            'data' => [
                'id' => $rec->id,
                'feature' => $rec->feature,
                'enabled' => (bool) $rec->enabled,
            ]
        ]);
    }

    
}
