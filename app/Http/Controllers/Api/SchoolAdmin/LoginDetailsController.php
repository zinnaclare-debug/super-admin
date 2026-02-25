<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserCredentialStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LoginDetailsController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'role' => ['nullable', Rule::in(['student', 'staff'])],
        ]);

        return response()->json([
            'data' => $this->buildRows($schoolId, $payload['role'] ?? null),
        ]);
    }

    public function download(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        $payload = $request->validate([
            'role' => ['nullable', Rule::in(['student', 'staff'])],
        ]);

        $rows = $this->buildRows($schoolId, $payload['role'] ?? null);
        $lines = [];
        $lines[] = $this->toCsvRow([
            'S/N',
            'Name',
            'Role',
            'Username',
            'Email',
            'Password',
            'Last Password Set',
        ]);

        foreach ($rows as $row) {
            $lines[] = $this->toCsvRow([
                $row['sn'],
                $row['name'],
                $row['role'],
                $row['username'],
                $row['email'],
                $row['password'],
                $row['last_password_set_at'],
            ]);
        }

        $fileName = 'user_login_details_' . now()->format('Ymd_His') . '.csv';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function buildRows(int $schoolId, ?string $role = null): array
    {
        $hasCredentialTable = Schema::hasTable('user_login_credentials');

        $users = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('role', ['student', 'staff'])
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($hasCredentialTable, fn ($q) => $q->with('loginCredential'))
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return $users->values()->map(function (User $user, int $index) use ($hasCredentialTable) {
            $credential = $hasCredentialTable ? $user->loginCredential : null;
            $password = UserCredentialStore::reveal($credential?->password_encrypted);

            return [
                'sn' => $index + 1,
                'user_id' => (int) $user->id,
                'name' => (string) $user->name,
                'role' => (string) $user->role,
                'username' => (string) ($user->username ?? ''),
                'email' => (string) ($user->email ?? ''),
                'password' => $password ?? '',
                'last_password_set_at' => optional($credential?->last_password_set_at)->toDateTimeString(),
            ];
        })->all();
    }

    private function toCsvRow(array $columns): string
    {
        $escaped = array_map(function ($value) {
            $text = (string) ($value ?? '');
            $text = str_replace('"', '""', $text);
            return '"' . $text . '"';
        }, $columns);

        return implode(',', $escaped);
    }
}
