<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;

class ResolveTenantFromSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = $this->normalizeHost($request->getHost());

        if ($this->isCentralDomain($host)) {
            return $next($request);
        }

        $subdomain = $this->extractSubdomain($host);
        if (! $subdomain) {
            return $next($request);
        }

        $school = School::query()
            ->where('subdomain', $subdomain)
            ->first();

        if (! $school) {
            return response()->json([
                'message' => 'School not found for this subdomain.',
            ], 404);
        }

        if ($school->status !== 'active') {
            return response()->json([
                'message' => 'This school account is suspended.',
            ], 403);
        }

        $requestKey = (string) config('tenancy.request_key', 'tenant_school');
        $request->attributes->set($requestKey, $school);

        app()->instance('tenant.school', $school);

        return $next($request);
    }

    private function isCentralDomain(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        $centralDomains = config('tenancy.central_domains', []);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($appHost) {
            $centralDomains[] = strtolower($appHost);
        }

        $centralDomains = array_values(array_unique(array_map(
            static fn ($domain) => strtolower(trim((string) $domain)),
            $centralDomains
        )));

        return in_array($host, $centralDomains, true);
    }

    private function extractSubdomain(string $host): ?string
    {
        $baseDomain = strtolower(trim((string) config('tenancy.base_domain')));

        if ($baseDomain !== '') {
            if ($host === $baseDomain || ! str_ends_with($host, '.' . $baseDomain)) {
                return null;
            }

            $subdomain = substr($host, 0, -strlen('.' . $baseDomain));
            if ($subdomain === '' || str_contains($subdomain, '.')) {
                return null;
            }

            return $subdomain;
        }

        $parts = explode('.', $host);
        if (count($parts) < 3) {
            return null;
        }

        return $parts[0];
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = rtrim($host, '.');

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return $host;
    }
}
