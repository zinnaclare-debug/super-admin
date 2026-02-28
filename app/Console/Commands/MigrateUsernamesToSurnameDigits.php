<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\UserCredentialStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateUsernamesToSurnameDigits extends Command
{
    protected $signature = 'users:migrate-usernames
        {--school_id= : Restrict to a single school id}
        {--all : Regenerate usernames even when they already match the new format}
        {--dry-run : Preview changes without saving}';

    protected $description = 'Migrate student/staff usernames to surname + random digits format';

    public function handle(): int
    {
        $schoolIdOption = $this->option('school_id');
        $schoolId = null;
        if ($schoolIdOption !== null && $schoolIdOption !== '') {
            if (!ctype_digit((string) $schoolIdOption) || (int) $schoolIdOption < 1) {
                $this->error('school_id must be a positive integer.');
                return self::INVALID;
            }
            $schoolId = (int) $schoolIdOption;
        }

        $migrateAll = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        $query = User::query()
            ->whereIn('role', ['student', 'staff'])
            ->orderBy('id');

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        $users = $query->get(['id', 'name', 'username', 'role', 'school_id']);
        if ($users->isEmpty()) {
            $this->info('No student/staff users found for migration.');
            return self::SUCCESS;
        }

        $usedUsernames = User::query()
            ->whereNotNull('username')
            ->pluck('username')
            ->map(fn ($username) => strtolower(trim((string) $username)))
            ->filter(fn ($username) => $username !== '')
            ->flip()
            ->all();

        $changes = [];
        foreach ($users as $user) {
            $current = strtolower(trim((string) ($user->username ?? '')));
            $looksCompliant = (bool) preg_match('/^[a-z0-9]{1,20}\d{2}$/', $current);
            if (!$migrateAll && $looksCompliant) {
                continue;
            }

            if ($current !== '' && isset($usedUsernames[$current])) {
                unset($usedUsernames[$current]);
            }

            $base = $this->usernameBaseFromName((string) $user->name);
            $newUsername = $this->generateUniqueUsernameCandidate($base, $usedUsernames);

            if ($newUsername === $current) {
                $usedUsernames[$current] = true;
                continue;
            }

            $usedUsernames[$newUsername] = true;

            $changes[] = [
                'id' => (int) $user->id,
                'old' => (string) ($user->username ?? ''),
                'new' => $newUsername,
            ];
        }

        if (empty($changes)) {
            $this->info('No usernames require migration.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Prepared %d username update(s)%s.',
            count($changes),
            $dryRun ? ' (dry-run)' : ''
        ));

        foreach ($changes as $index => $change) {
            if ($index >= 20) {
                $this->line('... output truncated, more rows pending');
                break;
            }
            $this->line(sprintf('#%d user_id=%d %s -> %s', $index + 1, $change['id'], $change['old'], $change['new']));
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        $hasCredentialTable = Schema::hasTable('user_login_credentials');

        DB::transaction(function () use ($changes, $hasCredentialTable) {
            foreach ($changes as $change) {
                /** @var User|null $user */
                $user = User::query()->find($change['id']);
                if (!$user) {
                    continue;
                }

                $user->username = $change['new'];
                $user->save();

                if ($hasCredentialTable) {
                    UserCredentialStore::sync($user, null, null);
                }
            }
        });

        $this->info('Username migration completed successfully.');

        return self::SUCCESS;
    }

    private function usernameBaseFromName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $surname = '';
        if (!empty($parts)) {
            $lastPart = end($parts);
            $surname = is_string($lastPart) ? $lastPart : '';
        }

        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', $surname) ?? '');
        if ($base === '') {
            $base = 'user';
        }

        return substr($base, 0, 20);
    }

    private function generateUniqueUsernameCandidate(string $base, array $used): string
    {
        $base = trim(strtolower($base));
        if ($base === '') {
            $base = 'user';
        }

        foreach ([2, 3, 4, 5] as $digits) {
            for ($attempt = 0; $attempt < 240; $attempt++) {
                $candidate = $base . $this->randomDigits($digits);
                if (!isset($used[$candidate])) {
                    return $candidate;
                }
            }
        }

        $counter = 1;
        do {
            $candidate = $base . str_pad((string) $counter, 6, '0', STR_PAD_LEFT);
            $counter++;
        } while (isset($used[$candidate]));

        return $candidate;
    }

    private function randomDigits(int $digits): string
    {
        $digits = max(1, $digits);
        $max = (10 ** $digits) - 1;

        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }
}

