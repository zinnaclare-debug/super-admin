<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Staff;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        $staff = Staff::where('user_id', $user->id)
            ->where('school_id', $user->school_id)
            ->first();

        $photoPath = $staff?->photo_path ?: $user->photo_path;

        $photoUrl = null;
        if ($photoPath) {
            $relativeOrAbsolute = Storage::disk('public')->url($photoPath);
            $photoUrl = str_starts_with($relativeOrAbsolute, 'http://')
                || str_starts_with($relativeOrAbsolute, 'https://')
                ? $relativeOrAbsolute
                : url($relativeOrAbsolute);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'school_id' => $user->school_id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'staff' => $staff,
                'photo_url' => $photoUrl,
            ]
        ]);
    }
}
