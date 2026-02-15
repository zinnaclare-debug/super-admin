<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return response()->json([
                'role' => 'super_admin',
                'dashboard' => 'system',
                'actions' => [
                    'manage_schools',
                    'toggle_features',
                    'view_all_data',
                ],
            ]);
        }

        if ($user->isSchoolAdmin()) {
            return response()->json([
                'role' => 'school_admin',
                'dashboard' => 'school',
                'school_id' => $user->school_id,
                'actions' => [
                    'manage_teachers',
                    'manage_students',
                    'toggle_school_features',
                ],
            ]);
        }

        if ($user->isTeacher()) {
            return response()->json([
                'role' => 'teacher',
                'dashboard' => 'teacher',
                'actions' => [
                    'create_cbt',
                    'manage_attendance',
                    'upload_materials',
                ],
            ]);
        }

        return response()->json([
            'role' => 'student',
            'dashboard' => 'student',
            'actions' => [
                'take_cbt',
                'view_results',
                'view_announcements',
            ],
        ]);
    }

    public function stats()
    {
        $activeUsers = Schema::hasColumn('users', 'is_active')
            ? User::where('is_active', true)->count()
            : User::count();

        $admins = User::whereIn('role', ['super_admin', 'school_admin'])->count();

        return response()->json([
            'schools' => School::count(),
            'active_users' => $activeUsers,
            'admins' => $admins,
        ]);
    }
}
