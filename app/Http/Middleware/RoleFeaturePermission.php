<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleFeaturePermission
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Super admin bypass
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // School admin bypass (they manage admin features)
        if ($user->role === 'school_admin') {
            return $next($request);
        }

        // Only enforce for staff/student
        $matrix = config('role_features', []);
        $allowed = $matrix[$user->role] ?? [];

        if (!in_array($feature, $allowed, true)) {
            abort(403, 'You do not have permission to access this feature.');
        }

        return $next($request);
    }
}
