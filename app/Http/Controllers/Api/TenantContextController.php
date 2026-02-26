<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantContextController extends Controller
{
    public function show(Request $request)
    {
        $tenantSchool = $request->attributes->get((string) config('tenancy.request_key', 'tenant_school'))
            ?? $this->resolveTenantSchool($request);

        if (! $tenantSchool) {
            return response()->json([
                'is_tenant' => false,
                'school' => null,
            ]);
        }

        return response()->json([
            'is_tenant' => true,
            'school' => [
                'id' => $tenantSchool->id,
                'name' => $tenantSchool->name,
                'subdomain' => $tenantSchool->subdomain,
                'logo_path' => $tenantSchool->logo_path,
                'logo_url' => $this->storageUrl($tenantSchool->logo_path),
                'contact_email' => $tenantSchool->contact_email,
                'contact_phone' => $tenantSchool->contact_phone,
            ],
        ]);
    }

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);
        return str_starts_with($relativeOrAbsolute, 'http://')
            || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
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
