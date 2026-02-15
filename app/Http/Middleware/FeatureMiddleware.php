<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FeatureMiddleware
{
    public function handle(Request $request, Closure $next, $feature)
    {
        $user = auth()->user();

        if (!$user || !$user->school) {
            return response()->json([
                'message' => 'School context not found'
            ], 403);
        }

        $enabled = $user->school->features()
            ->where('feature', $feature)
            ->where('enabled', true)
            ->exists();

        if (!$enabled) {
            return response()->json([
                'message' => 'This feature is disabled for your school'
            ], 403);
        }

        return $next($request);
    }
}
