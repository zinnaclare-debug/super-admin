<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;

class EnsureUserBelongsToTenant
{
    public function handle(Request $request, Closure $next)
    {
        $requestKey = (string) config('tenancy.request_key', 'tenant_school');
        $tenantSchool = $request->attributes->get($requestKey);
        if (! $tenantSchool) {
            $tenantSchool = $this->resolveTenantSchool($request);
            if ($tenantSchool) {
                $request->attributes->set($requestKey, $tenantSchool);
            }
        }

        $user = $request->user() ?? auth('sanctum')->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->role === 'super_admin' && $tenantSchool) {
            return response()->json([
                'message' => 'Super admin access is only allowed on the central domain.',
            ], 403);
        }

        if (! $tenantSchool) {
            if (
                (bool) config('tenancy.require_subdomain_for_school_users', false)
                && ! empty($user->school_id)
            ) {
                return response()->json([
                    'message' => 'Use your school subdomain to access this account.',
                ], 403);
            }

            return $next($request);
        }

        if ((int) $user->school_id !== (int) $tenantSchool->id) {
            return response()->json([
                'message' => 'This account does not belong to this school subdomain.',
            ], 403);
        }

        return $next($request);
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

        return School::query()->where('subdomain', $subdomain)->first();
    }
}
