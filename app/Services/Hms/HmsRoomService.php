<?php

namespace App\Services\Hms;

use App\Models\User;
use App\Models\VirtualClass;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HmsRoomService
{
    public function isConfigured(): bool
    {
        return $this->appAccessKey() !== ''
            && $this->appSecret() !== ''
            && $this->templateId() !== ''
            && $this->staffRole() !== ''
            && $this->studentRole() !== '';
    }

    public function ensureRoomProvisioned(VirtualClass $virtualClass): VirtualClass
    {
        if ($virtualClass->provider !== '100ms' || $virtualClass->class_type !== 'live') {
            return $virtualClass;
        }

        if ($virtualClass->provider_room_id && $virtualClass->staff_room_code && $virtualClass->student_room_code) {
            return $virtualClass;
        }

        $this->assertConfigured();

        $room = $this->createRoom($virtualClass);
        $roomCodes = $this->createRoomCodes((string) ($room['id'] ?? ''));

        $staffRoomCode = $this->findRoomCode($roomCodes, $this->staffRole());
        $studentRoomCode = $this->findRoomCode($roomCodes, $this->studentRole());

        if (!$staffRoomCode || !$studentRoomCode) {
            throw ValidationException::withMessages([
                'provider' => ['100ms room codes could not be generated for the configured staff/student roles.'],
            ]);
        }

        $virtualClass->forceFill([
            'provider_room_id' => (string) ($room['id'] ?? ''),
            'staff_room_code' => $staffRoomCode,
            'student_room_code' => $studentRoomCode,
            'meeting_link' => $this->buildInternalMeetingLink($virtualClass),
        ])->save();

        return $virtualClass->fresh();
    }

    public function buildSessionPayload(VirtualClass $virtualClass, User $user, string $audience): array
    {
        $virtualClass = $this->ensureRoomProvisioned($virtualClass);

        $roomCode = $audience === 'staff'
            ? $virtualClass->staff_room_code
            : $virtualClass->student_room_code;

        if (!$roomCode) {
            throw ValidationException::withMessages([
                'provider' => ['No 100ms room code is available for this live class yet.'],
            ]);
        }

        return [
            'virtual_class_id' => $virtualClass->id,
            'room_id' => $virtualClass->provider_room_id,
            'room_code' => $roomCode,
            'display_name' => $this->displayNameForUser($user),
            'user_id' => (string) $user->id,
            'title' => $virtualClass->title,
            'role' => $audience === 'staff' ? $this->staffRole() : $this->studentRole(),
            'staff_role_name' => $this->staffRole(),
            'student_role_name' => $this->studentRole(),
        ];
    }

    private function createRoom(VirtualClass $virtualClass): array
    {
        return $this->request('post', '/rooms', [
            'name' => $this->roomName($virtualClass),
            'description' => $virtualClass->title,
            'template_id' => $this->templateId(),
        ]);
    }

    private function createRoomCodes(string $roomId): array
    {
        return $this->request('post', "/room-codes/room/{$roomId}");
    }

    private function findRoomCode(array $payload, string $role): ?string
    {
        $rows = $payload['data'] ?? [];

        foreach ($rows as $row) {
            if (($row['role'] ?? null) === $role && ($row['enabled'] ?? false) && !empty($row['code'])) {
                return (string) $row['code'];
            }
        }

        return null;
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $response = Http::acceptJson()
            ->withToken($this->managementToken())
            ->timeout(20)
            ->{$method}(rtrim($this->baseUrl(), '/') . $path, $payload);

        if ($response->failed()) {
            $message = (string) ($response->json('message') ?: $response->body() ?: '100ms request failed.');

            throw ValidationException::withMessages([
                'provider' => [$message],
            ]);
        }

        return $response->json() ?: [];
    }

    private function buildInternalMeetingLink(VirtualClass $virtualClass): string
    {
        return rtrim((string) config('app.url', 'http://localhost'), '/') . '/virtual-class/live/' . $virtualClass->id;
    }

    private function roomName(VirtualClass $virtualClass): string
    {
        return 'school-' . $virtualClass->school_id . '-live-class-' . $virtualClass->id;
    }

    private function displayNameForUser(User $user): string
    {
        $name = trim((string) ($user->name ?: $user->username ?: $user->email ?: 'Participant'));

        return $name !== '' ? $name : 'Participant';
    }

    private function assertConfigured(): void
    {
        if ($this->isConfigured()) {
            return;
        }

        throw ValidationException::withMessages([
            'provider' => ['100ms is not configured yet. Add HMS_APP_ACCESS_KEY, HMS_APP_SECRET, HMS_TEMPLATE_ID, HMS_STAFF_ROLE and HMS_STUDENT_ROLE to your environment.'],
        ]);
    }

    private function managementToken(): string
    {
        $issuedAt = time();
        $payload = [
            'access_key' => $this->appAccessKey(),
            'type' => 'management',
            'version' => 2,
            'jti' => (string) Str::uuid(),
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + 86400,
        ];

        return $this->encodeJwt($payload, $this->appSecret());
    }

    private function encodeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function baseUrl(): string
    {
        return (string) config('services.hms.base_url', 'https://api.100ms.live/v2');
    }

    private function appAccessKey(): string
    {
        return trim((string) config('services.hms.app_access_key', ''));
    }

    private function appSecret(): string
    {
        return trim((string) config('services.hms.app_secret', ''));
    }

    private function templateId(): string
    {
        return trim((string) config('services.hms.template_id', ''));
    }

    private function staffRole(): string
    {
        return trim((string) config('services.hms.staff_role', 'teacher'));
    }

    private function studentRole(): string
    {
        return trim((string) config('services.hms.student_role', 'student'));
    }
}
