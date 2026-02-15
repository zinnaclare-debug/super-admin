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

        $photoUrl = null;
        if ($staffProfile->photo_path && Storage::disk('public')->exists($staffProfile->photo_path)) {
            $photoUrl = Storage::disk('public')->url($staffProfile->photo_path);
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
                    'photo_path' => $staffProfile->photo_path,
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

        // Delete old photo if exists
        if ($staffProfile->photo_path && Storage::disk('public')->exists($staffProfile->photo_path)) {
            Storage::disk('public')->delete($staffProfile->photo_path);
        }

        // Store new photo
       $schoolId = $user->school_id;

$path = $request->file('photo')->store(
    "schools/{$schoolId}/staff",
    'public'
);

        $staffProfile->update(['photo_path' => $path]);

        $photoUrl = Storage::disk('public')->url($path);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'data' => [
                'photo_path' => $path,
                'photo_url' => $photoUrl,
            ]
        ], 200);
    }
}
