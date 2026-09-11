<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\SchoolPublicWebsiteData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileSchoolController extends Controller
{
    public function resolve(Request $request)
    {
        $validated = $request->validate([
            'school_code' => ['required', 'string', 'size:8', 'regex:/^[A-Za-z0-9]{3}-[A-Za-z0-9]{4}$/'],
        ]);

        $schoolCode = strtoupper($validated['school_code']);
        $school = School::query()
            ->where('school_code', $schoolCode)
            ->where('status', 'active')
            ->first();

        if (! $school) {
            throw ValidationException::withMessages([
                'school_code' => ['School code was not found or this school is not active.'],
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $school->id,
                'name' => $school->name,
                'school_code' => $school->school_code,
                'subdomain' => $school->subdomain,
                'api_base_url' => $this->tenantUrl($request, $school),
                'logo_path' => $school->logo_path,
                'location' => $school->location,
                'school_location' => $school->location,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'website_content' => SchoolPublicWebsiteData::normalizeWebsiteContent(
                    $school->website_content,
                    $school
                ),
            ],
        ]);
    }

    private function tenantUrl(Request $request, School $school): string
    {
        $baseDomain = trim((string) config('tenancy.base_domain'));
        if ($baseDomain === '') {
            $baseDomain = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        }

        $scheme = (string) parse_url((string) config('app.url'), PHP_URL_SCHEME);
        $scheme = $scheme !== '' ? $scheme : $request->getScheme();

        return sprintf('%s://%s.%s', $scheme, $school->subdomain, $baseDomain);
    }
}