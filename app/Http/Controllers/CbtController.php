<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CbtExam;
use App\Enums\SchoolFeatureEnum;

class CbtController extends Controller
{
    /**
     * List CBT exams for the current school
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Role check (teacher or school admin only)
        abort_unless(
            in_array($user->role, ['teacher', 'school_admin']),
            403,
            'Unauthorized role'
        );

        // Feature toggle check
        abort_unless(
            schoolFeatureEnabled(SchoolFeatureEnum::CBT->value),
            403,
            'CBT feature is disabled for this school'
        );

        // Return only this school's CBT exams
        return CbtExam::where('school_id', $user->school_id)->get();
    }
}
