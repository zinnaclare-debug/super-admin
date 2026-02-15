<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolFeature;

class SchoolController extends Controller
{
    /**
     * Delete a school (DESTROY)
     */
    public function destroy(School $school)
    {
        // Optionally, handle related data cleanup here
        $school->delete();
        return response()->json(['message' => 'School deleted successfully.']);
    }
    /**
     * ✅ LIST ALL SCHOOLS (USED BY OVERVIEW & SCHOOLS TABLE)
     */
    public function index()
    {
        $schools = School::with([
            'admin:id,name,email,school_id',
            'features'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'data' => $schools
        ]);
    }

    /**
     * ✅ STORE (Create school directly - includes username_prefix)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:schools,email',
            'username_prefix'  => 'required|string|max:50|unique:schools,username_prefix',
            'status'           => 'nullable|in:active,suspended',
        ]);

        $school = School::create($validated + [
            'slug'      => Str::slug($validated['name']),
            'subdomain' => Str::slug($validated['name']),
            'status'    => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'data'    => $school,
            'message' => 'School created successfully'
        ], 201);
    }

    /**
     * ✅ CREATE SCHOOL + ADMIN (MULTI-TENANT ENTRY POINT)
     */
    public function createWithAdmin(Request $request)
    {
        $validated = $request->validate([
            'school_name'  => 'required|string|max:255',
            'school_email' => 'required|email|unique:schools,email',
            'admin_name'   => 'required|string|max:255',
            'admin_email'  => 'required|email|unique:users,email',
        ]);

        return DB::transaction(function () use ($validated) {

            // 1️⃣ Create school (tenant)
            $school = School::create([
                'name'             => $validated['school_name'],
                'email'            => $validated['school_email'],
                'username_prefix'  => Str::slug($validated['school_name']),
                'slug'             => Str::slug($validated['school_name']),
                'subdomain'        => Str::slug($validated['school_name']),
                'status'           => 'active',
            ]);

            // 2️⃣ Generate password (shown ONCE)
            $plainPassword = Str::random(10);

            // 3️⃣ Create school admin
            $admin = User::create([
                'name'      => $validated['admin_name'],
                'email'     => $validated['admin_email'],
                'password'  => Hash::make($plainPassword),
                'role'      => 'school_admin',
                'school_id' => $school->id,
            ]);

            // 4️⃣ Seed features (use updateOrCreate to avoid duplicates if observer already created some)
// 4️⃣ Seed features (GENERAL + ADMIN)

// 4️⃣ Seed features (GENERAL + ADMIN)
$defs = config('features.definitions');

foreach ($defs as $def) {
    SchoolFeature::updateOrCreate(
        [
            'school_id' => $school->id,
            'feature'   => $def['key'],
        ],
        [
            'enabled'   => true, // super admin can later disable
            'category'  => $def['category'] ?? 'general',
        ]
    );
}




            return response()->json([
                'school'   => $school,
                'admin'    => $admin,
                'password' => $plainPassword, // show ONCE
            ], 201);
        });
    }

    /**
     * ✅ ACTIVATE / SUSPEND SCHOOL
     */
    public function toggle(School $school)
    {
        $school->status = $school->status === 'active'
            ? 'suspended'
            : 'active';

        $school->save();

        return response()->json([
            'message' => 'School status updated',
            'status'  => $school->status
        ]);
    }

    /**
     * Toggle result publication for a school (student-side visibility gate)
     */
    public function toggleResultsPublish(School $school)
    {
        $school->results_published = !$school->results_published;
        $school->save();

        return response()->json([
            'message' => 'School results publication updated',
            'results_published' => (bool) $school->results_published,
        ]);
    }
}
