<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolFeature;
use Illuminate\Http\Request;


class SchoolFeatureController extends Controller
{
public function toggle(Request $request, School $school)
{
    $request->validate([
        'feature' => 'required|string',
    ]);

    $record = SchoolFeature::firstOrCreate(
        ['school_id' => $school->id, 'feature' => $request->feature],
        ['enabled' => true]
    );

    $record->update(['enabled' => !$record->enabled]);

    return response()->json($record);
}
}

