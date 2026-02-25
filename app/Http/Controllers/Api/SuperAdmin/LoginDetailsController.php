<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserCredentialStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LoginDetailsController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $this->buildRows(),
        ]);
    }

    public function download(Request $request)
    {
        $rows = $this->buildRows();
        $lines = [];
        $lines[] = $this->toCsvRow([
            'S/N',
            'School',
            'School Admin Name',
            'Username',
            'Email',
            'Password',
            'Last Password Set',
        ]);

        foreach ($rows as $row) {
            $lines[] = $this->toCsvRow([
                $row['sn'],
                $row['school_name'],
                $row['name'],
                $row['username'],
                $row['email'],
                $row['password'],
                $row['last_password_set_at'],
            ]);
        }

        $fileName = 'school_admin_login_details_' . now()->format('Ymd_His') . '.csv';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function buildRows(): array
    {
        $hasCredentialTable = Schema::hasTable('user_login_credentials');

        $query = User::query()
            ->where('role', User::ROLE_SCHOOL_ADMIN)
            ->with('school:id,name')
            ->orderBy('name');

        if ($hasCredentialTable) {
            $query->with('loginCredential');
        }

        $users = $query->get();

        return $users->values()->map(function (User $user, int $index) use ($hasCredentialTable) {
            $credential = $hasCredentialTable ? $user->loginCredential : null;
            $password = UserCredentialStore::reveal($credential?->password_encrypted);

            return [
                'sn' => $index + 1,
                'user_id' => (int) $user->id,
                'school_id' => (int) ($user->school_id ?? 0),
                'school_name' => (string) ($user->school?->name ?? '-'),
                'name' => (string) $user->name,
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

