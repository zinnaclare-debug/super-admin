<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLoginCredential;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class UserCredentialStore
{
    public static function sync(User $user, ?string $plainPassword = null, ?int $actorUserId = null): UserLoginCredential
    {
        $payload = [
            'school_id' => (int) $user->school_id,
            'role' => (string) $user->role,
            'name' => (string) $user->name,
            'username' => $user->username ? (string) $user->username : null,
            'email' => $user->email ? (string) $user->email : null,
        ];

        if ($actorUserId) {
            $payload['updated_by_user_id'] = $actorUserId;
        }

        $cleanPassword = trim((string) $plainPassword);
        if ($cleanPassword !== '') {
            $payload['password_encrypted'] = Crypt::encryptString($cleanPassword);
            $payload['last_password_set_at'] = now();
        }

        $credential = UserLoginCredential::query()->firstOrNew(['user_id' => (int) $user->id]);
        if (!$credential->exists && $actorUserId) {
            $payload['created_by_user_id'] = $actorUserId;
        }

        $credential->fill($payload);
        $credential->save();

        return $credential;
    }

    public static function reveal(?string $encrypted): ?string
    {
        if (!$encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }
}

