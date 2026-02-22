<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
        DB::transaction(function () use ($school) {
            // Ensure school users are removed so their unique emails can be reused.
            User::where('school_id', $school->id)->delete();

            // Clean up explicit school feature rows.
            SchoolFeature::where('school_id', $school->id)->delete();

            $school->delete();
        });

        $this->clearApplicationCache();

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
        $request->merge([
            'subdomain' => $this->normalizeSubdomain($request->input('subdomain')),
        ]);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:schools,email',
            'username_prefix'  => 'required|string|max:50|unique:schools,username_prefix',
            'subdomain'        => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+$/', 'unique:schools,subdomain'],
            'status'           => 'nullable|in:active,suspended',
        ]);

        $school = School::create($validated + [
            'slug'      => Str::slug($validated['name']),
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
        $request->merge([
            'subdomain' => $this->normalizeSubdomain($request->input('subdomain')),
        ]);

        $validated = $request->validate([
            'school_name'  => 'required|string|max:255',
            'school_email' => 'required|email|unique:schools,email',
            'admin_name'   => 'required|string|max:255',
            'admin_email'  => 'required|email|unique:users,email',
            'subdomain'    => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+$/', 'unique:schools,subdomain'],
        ]);

        return DB::transaction(function () use ($validated) {

            // 1️⃣ Create school (tenant)
            $school = School::create([
                'name'             => $validated['school_name'],
                'email'            => $validated['school_email'],
                'username_prefix'  => Str::slug($validated['school_name']),
                'slug'             => Str::slug($validated['school_name']),
                'subdomain'        => $validated['subdomain'],
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

    private function normalizeSubdomain(?string $subdomain): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $subdomain))) ?? '';
    }

    private function clearApplicationCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            Log::warning('Cache flush failed after school deletion: ' . $e->getMessage());
        }
    }
}
