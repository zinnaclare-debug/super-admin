<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffProfileController extends Controller
{
    /**
     * GET /api/staff/profile
     * Fetch the current staff member's profile data
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->role !== 'staff') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $staffProfile = $user->staffProfile;

        if (!$staffProfile) {
            return response()->json(['message' => 'Staff profile not found'], 404);
        }

        // Get classes where this staff is the class teacher
        $classes = SchoolClass::where('class_teacher_user_id', $user->id)
            ->where('school_id', $user->school_id)
            ->select('id', 'name', 'level', 'academic_session_id')
            ->get();

        $photoPath = $staffProfile->photo_path ?: $user->photo_path;

        $photoUrl = null;
        if ($photoPath) {
            $photoUrl = $this->publicUrl($request, $photoPath);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'staff' => [
                    'sex' => $staffProfile->sex,
                    'dob' => $staffProfile->dob,
                    'address' => $staffProfile->address,
                    'position' => $staffProfile->position,
                    'education_level' => $staffProfile->education_level,
                    'photo_path' => $photoPath,
                    'photo_url' => $photoUrl,
                ],
                'classes' => $classes,
            ]
        ]);
    }

    /**
     * POST /api/staff/profile/photo
     * Upload staff profile photo
     */
    public function uploadPhoto(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->role !== 'staff') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $staffProfile = $user->staffProfile;
        
        if (!$staffProfile) {
            return response()->json(['message' => 'Staff profile not found'], 404);
        }

        $oldPath = $staffProfile->photo_path ?: $user->photo_path;

        // Store new photo
       $schoolId = $user->school_id;

$path = $request->file('photo')->store(
    "schools/{$schoolId}/staff",
    'public'
);

        $staffProfile->update(['photo_path' => $path]);
        $user->photo_path = $path;
        $user->save();

        if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $photoUrl = $this->publicUrl($request, $path);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'data' => [
                'photo_path' => $path,
                'photo_url' => $photoUrl,
            ]
        ], 200);
    }

    /**
     * GET /api/staff/profile/photo
     * Stream current staff profile photo (auth-protected fallback)
     */
    public function photo(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->role !== 'staff') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $staffProfile = $user->staffProfile;
        $photoPath = $staffProfile?->photo_path ?: $user->photo_path;

        if (!$photoPath) {
            return response()->json(['message' => 'Photo not found'], 404);
        }

        if (!Storage::disk('public')->exists($photoPath)) {
            return response()->json(['message' => 'Photo file missing'], 404);
        }

        $fullPath = Storage::disk('public')->path($photoPath);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function publicUrl(Request $request, string $path): string
    {
        $relativeOrAbsolute = Storage::disk('public')->url($path);

        if (str_starts_with($relativeOrAbsolute, 'http://') || str_starts_with($relativeOrAbsolute, 'https://')) {
            return $relativeOrAbsolute;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($relativeOrAbsolute, '/');
    }
}
