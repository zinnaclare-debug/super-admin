<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        if (!$user->is_active) {
    Auth::logout();
    return response()->json(['message' => 'Account disabled'], 403);
}

        $requestKey = (string) config('tenancy.request_key', 'tenant_school');
        $tenantSchool = $request->attributes->get($requestKey) ?? $this->resolveTenantSchool($request);

        if ($tenantSchool) {
            if ($user->role === 'super_admin' || empty($user->school_id)) {
                Auth::logout();
                return response()->json([
                    'message' => 'Super admin accounts must sign in from the central domain.',
                ], 403);
            }

            if ((int) $user->school_id !== (int) $tenantSchool->id) {
                Auth::logout();
                return response()->json([
                    'message' => 'This account does not belong to this school subdomain.',
                ], 403);
            }
        } elseif (
            (bool) config('tenancy.require_subdomain_for_school_users', false)
            && !empty($user->school_id)
        ) {
            Auth::logout();
            return response()->json([
                'message' => 'Use your school subdomain to sign in.',
            ], 403);
        }

        // Keep existing tokens so the same account can stay logged in on multiple devices.
        // If you later want limits, prune old tokens with a retention policy instead of deleting all.
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'school_id' => $user->school_id,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    private function resolveTenantSchool(Request $request): ?School
    {
        $host = strtolower(trim($request->getHost()));
        $host = rtrim($host, '.');
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        $centralDomains = array_values(array_unique(array_map(
            static fn ($domain) => strtolower(trim((string) $domain)),
            (array) config('tenancy.central_domains', [])
        )));

        if (in_array($host, $centralDomains, true) || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $baseDomain = strtolower(trim((string) config('tenancy.base_domain')));
        if ($baseDomain !== '') {
            if ($host === $baseDomain || ! str_ends_with($host, '.' . $baseDomain)) {
                return null;
            }

            $subdomain = substr($host, 0, -strlen('.' . $baseDomain));
            if ($subdomain === '' || str_contains($subdomain, '.')) {
                return null;
            }
        } else {
            $parts = explode('.', $host);
            if (count($parts) < 3) {
                return null;
            }
            $subdomain = $parts[0];
        }

        $school = School::query()->where('subdomain', $subdomain)->first();
        if ($school) {
            $request->attributes->set((string) config('tenancy.request_key', 'tenant_school'), $school);
        }

        return $school;
    }
}
