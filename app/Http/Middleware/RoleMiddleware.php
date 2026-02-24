<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Prefer Sanctum token-authenticated user for API role checks.
        $user = $request->user('sanctum') ?? $request->user();

        // Debug: log the current user role and requested path (temporary)
        try {
            Log::info('RoleMiddleware check', [
                'path' => $request->path(),
                'user_id' => $user?->id ?? null,
                'user_role' => $user?->role ?? null,
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors in middleware
        }

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return $next($request);
    }
}
