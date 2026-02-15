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

        $photoUrl = null;
        if ($staff && $staff->photo_path) {
            $photoUrl = Storage::url($staff->photo_path);
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
