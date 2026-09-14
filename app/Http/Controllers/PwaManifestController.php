<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;

class PwaManifestController extends Controller
{
    public function show(Request $request)
    {
        $school = $this->resolveTenantSchool($request);
        $schoolName = trim((string) ($school?->name ?? ''));
        $name = $schoolName !== '' ? $schoolName : 'LYT School Portal';
        $logoPath = trim((string) ($school?->logo_path ?? ''));
        $iconUrl = $logoPath !== '' ? '/storage/' . ltrim($logoPath, '/') : '/lyt-logo.png';
        $websiteContent = is_array($school?->website_content) ? $school->website_content : [];
        $themeColor = (string) ($websiteContent['primary_color'] ?? '#082f49');

        return response()->json([
            'name' => $name,
            'short_name' => $name,
            'id' => '/',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $themeColor,
            'icons' => [
                ['src' => $iconUrl, 'type' => $this->iconMimeType($logoPath)],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function resolveTenantSchool(Request $request): ?School
    {
        $host = strtolower(rtrim(trim($request->getHost()), '.'));
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
        if ($baseDomain === '' || $host === $baseDomain || !str_ends_with($host, '.' . $baseDomain)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen('.' . $baseDomain));
        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return School::query()
            ->where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();
    }

    private function iconMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }
}