<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct()
    {
        // Super Admin ONLY
        $this->middleware(function ($request, $next) {
            abort_unless(
                auth()->check() && auth()->user()->isSuperAdmin(),
                403,
                'Only Super Admin can manage schools'
            );

            return $next($request);
        });
    }

    public function index()
    {
        return response()->json(
            School::latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'required|string|unique:schools,slug',
            'subdomain' => 'required|string|unique:schools,subdomain',
        ]);

        $school = School::create($validated);

        return response()->json([
            'message' => 'School created successfully',
            'school'  => $school,
        ], 201);
    }

    public function toggleStatus(School $school)
{
    $school->update([
        'status' => !$school->status,
    ]);

    return response()->json([
        'status' => true,
        'data' => $school,
    ]);
}


}
