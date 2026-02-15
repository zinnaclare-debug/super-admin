<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        // Super Admin bypass
        if ($user && $user->role === 'super_admin') {
            return $next($request);
        }

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        if (!$user->school) {
            abort(403, 'No school assigned to this account.');
        }

        if (!$user->school->hasFeature($feature)) {
            abort(403, 'This feature is disabled for your school.');
        }

        return $next($request);
    }
}
