<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolFeature;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $school = School::find($schoolId);

        $genderStats = User::query()
            ->from('users')
            ->leftJoin('students', 'students.user_id', '=', 'users.id')
            ->where('users.school_id', $schoolId)
            ->where('users.role', 'student')
            ->selectRaw('COUNT(users.id) as total_students')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(students.sex, ''))) IN ('m', 'male') THEN 1 ELSE 0 END) as male_students")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(students.sex, ''))) IN ('f', 'female') THEN 1 ELSE 0 END) as female_students")
            ->first();

        $students = (int) ($genderStats->total_students ?? 0);
        $maleStudents = (int) ($genderStats->male_students ?? 0);
        $femaleStudents = (int) ($genderStats->female_students ?? 0);
        $unspecifiedStudents = max(0, $students - ($maleStudents + $femaleStudents));

        $staff = User::where('school_id', $schoolId)
            ->where('role', 'staff')
            ->count();

        $enabledModules = SchoolFeature::where('school_id', $schoolId)
            ->where('enabled', true)
            ->count();

        return response()->json([
            'school_name' => $school?->name,
            'school_location' => $school?->location,
            'school_logo_url' => $this->storageUrl($school?->logo_path),
            'head_of_school_name' => $school?->head_of_school_name,
            'head_signature_url' => $this->storageUrl($school?->head_signature_path),
            'students' => $students,
            'male_students' => $maleStudents,
            'female_students' => $femaleStudents,
            'unspecified_students' => $unspecifiedStudents,
            'staff' => $staff,
            'enabled_modules' => $enabledModules,
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $school = School::find($schoolId);

        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $file = $request->file('logo');
        $ext = $file->getClientOriginalExtension();
        $path = $file->storeAs("schools/{$schoolId}/branding", "logo.{$ext}", 'public');

        $school->logo_path = $path;
        $school->save();

        return response()->json([
            'message' => 'School logo uploaded successfully',
            'school_logo_url' => $this->storageUrl($school->logo_path),
            'school_name' => $school->name,
        ]);
    }

    public function upsertBranding(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $school = School::find($schoolId);

        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $payload = $request->validate([
            'head_of_school_name' => 'nullable|string|max:255',
            'school_location' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'head_signature' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $hasNameField = $request->has('head_of_school_name');
        $hasLocationField = $request->has('school_location');
        $hasLogoFile = $request->hasFile('logo');
        $hasSignatureFile = $request->hasFile('head_signature');

        if (!$hasNameField && !$hasLocationField && !$hasLogoFile && !$hasSignatureFile) {
            return response()->json([
                'message' => 'Provide head_of_school_name, school_location, logo, or head_signature.',
            ], 422);
        }

        if ($hasNameField) {
            $name = trim((string) ($payload['head_of_school_name'] ?? ''));
            $school->head_of_school_name = $name !== '' ? $name : null;
        }

        if ($hasLocationField) {
            $location = trim((string) ($payload['school_location'] ?? ''));
            $school->location = $location !== '' ? $location : null;
        }

        if ($hasLogoFile) {
            $logo = $request->file('logo');
            $logoExt = $logo->getClientOriginalExtension();
            $school->logo_path = $logo->storeAs("schools/{$schoolId}/branding", "logo.{$logoExt}", 'public');
        }

        if ($hasSignatureFile) {
            $signature = $request->file('head_signature');
            $signatureExt = $signature->getClientOriginalExtension();
            $school->head_signature_path = $signature->storeAs(
                "schools/{$schoolId}/branding",
                "head_signature.{$signatureExt}",
                'public'
            );
        }

        $school->save();

        return response()->json([
            'message' => 'School branding updated successfully',
            'data' => [
                'school_name' => $school->name,
                'school_location' => $school->location,
                'school_logo_url' => $this->storageUrl($school->logo_path),
                'head_of_school_name' => $school->head_of_school_name,
                'head_signature_url' => $this->storageUrl($school->head_signature_path),
            ],
        ]);
    }

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);
        return str_starts_with($relativeOrAbsolute, 'http://')
            || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
    }
}
