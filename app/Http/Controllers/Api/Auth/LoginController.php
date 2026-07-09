<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAdminLoginAudit;
use App\Models\Student;
use App\Models\User;
use App\Support\DeviceInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if ($user->role === 'student' && Schema::hasColumn('students', 'status')) {
            $studentStatus = Student::query()
                ->where('user_id', (int) $user->id)
                ->value('status');

            if ($studentStatus === 'graduated') {
                return response()->json([
                    'code' => 'graduated',
                    'message' => 'Congratulations, you have graduated.',
                ], 403);
            }
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account disabled'], 403);
        }

        if ($user->role !== 'super_admin' && !empty($user->school_id)) {
            $userSchoolStatus = School::query()
                ->where('id', (int) $user->school_id)
                ->value('status');

            if ($userSchoolStatus !== 'active') {
                return response()->json([
                    'code' => 'school_suspended',
                    'message' => 'This school account is suspended.',
                ], 403);
            }
        }

        $requestKey = (string) config('tenancy.request_key', 'tenant_school');
        $tenantSchool = $request->attributes->get($requestKey) ?? $this->resolveTenantSchool($request);

        if ($tenantSchool) {
            if ($user->role === 'super_admin' || empty($user->school_id)) {
                return response()->json([
                    'message' => 'Super admin accounts must sign in from the central domain.',
                ], 403);
            }

            if ((int) $user->school_id !== (int) $tenantSchool->id) {
                return response()->json([
                    'message' => 'This account does not belong to this school subdomain.',
                ], 403);
            }
        } elseif (
            (bool) config('tenancy.require_subdomain_for_school_users', false)
            && !empty($user->school_id)
        ) {
            return response()->json([
                'message' => 'Use your school subdomain to sign in.',
            ], 403);
        }

        $loginLimitResponse = $this->ensureSchoolAdminCanLogin($user);
        if ($loginLimitResponse) {
            return $loginLimitResponse;
        }

        $newAccessToken = $user->createToken($this->authTokenName($request, $user));
        $token = $newAccessToken->plainTextToken;
        $this->recordSchoolAdminLogin($request, $user, (int) $newAccessToken->accessToken->id);
        $schoolName = '';
        if ($tenantSchool) {
            $schoolName = (string) ($tenantSchool->name ?? '');
        } elseif (!empty($user->school_id)) {
            $schoolName = (string) (School::query()
                ->where('id', $user->school_id)
                ->value('name') ?? '');
        }

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'school_id' => $user->school_id,
                'school_name' => $schoolName,
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

    private function authTokenName(Request $request, User $user): string
    {
        if ($user->role !== User::ROLE_SCHOOL_ADMIN) {
            return 'auth-token';
        }

        $deviceInfo = DeviceInfo::fromUserAgent(substr((string) $request->userAgent(), 0, 2000));
        $label = implode(' | ', array_filter([
            $deviceInfo['device_type'] ?? null,
            $deviceInfo['device_model'] ?? null,
            $deviceInfo['browser'] ?? null,
            $deviceInfo['platform'] ?? null,
        ]));

        return Str::limit('school-admin login' . ($label ? ': ' . $label : ''), 120, '');
    }

    private function ensureSchoolAdminCanLogin(User $user)
    {
        if ($user->role !== User::ROLE_SCHOOL_ADMIN || empty($user->school_id)) {
            return null;
        }

        $school = School::query()->find((int) $user->school_id);
        $maximumDevices = $this->schoolAdminLoginLimit($school);
        $activeDevices = $user->tokens()->count();

        if ($activeDevices >= $maximumDevices) {
            return response()->json([
                'code' => 'school_admin_login_limit_reached',
                'message' => "Login limit reached. This school admin account already has {$activeDevices} active device(s). Ask Super Admin to log out an active device or increase the allowed login limit.",
                'allowed_logins' => $maximumDevices,
                'active_logins' => $activeDevices,
            ], 403);
        }

        return null;
    }

    private function schoolAdminLoginLimit(?School $school): int
    {
        if (!$school || !Schema::hasColumn('schools', 'school_admin_login_limit')) {
            return 2;
        }

        return max(1, min(50, (int) ($school->school_admin_login_limit ?: 2)));
    }

    private function recordSchoolAdminLogin(Request $request, User $user, ?int $tokenId = null): void
    {
        if ($user->role !== User::ROLE_SCHOOL_ADMIN || empty($user->school_id)) {
            return;
        }

        try {
            $userAgent = substr((string) $request->userAgent(), 0, 2000);
            $deviceInfo = DeviceInfo::fromUserAgent($userAgent);

            $auditPayload = [
                'school_id' => (int) $user->school_id,
                'user_id' => (int) $user->id,
                'ip_address' => $request->ip(),
                'forwarded_ip' => substr((string) $request->header('x-forwarded-for', ''), 0, 255) ?: null,
                'user_agent' => $userAgent,
                'device_info' => $deviceInfo,
                'device_type' => $deviceInfo['device_type'] ?? null,
                'device_model' => $deviceInfo['device_model'] ?? null,
                'browser' => $deviceInfo['browser'] ?? null,
                'platform' => $deviceInfo['platform'] ?? null,
                'pc_name' => null,
                'location_label' => null,
                'logged_in_at' => now(),
            ];

            if (Schema::hasColumn('school_admin_login_audits', 'personal_access_token_id')) {
                $auditPayload['personal_access_token_id'] = $tokenId;
            }

            SchoolAdminLoginAudit::query()->create($auditPayload);
        } catch (\Throwable $e) {
            Log::warning('School admin login audit failed: ' . $e->getMessage(), [
                'user_id' => (int) $user->id,
                'school_id' => (int) $user->school_id,
            ]);
        }
    }
}
