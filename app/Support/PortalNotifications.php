<?php

namespace App\Support;

use App\Jobs\SendPortalPushNotification;
use App\Models\Announcement;
use App\Models\PortalNotification;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;

class PortalNotifications
{
    public static function announcement(Announcement $announcement): void
    {
        $school = School::query()->find($announcement->school_id);
        if (!$school) {
            return;
        }

        $recipients = User::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_STUDENT])
            ->with(['staffProfile:id,user_id,education_level', 'studentProfile:id,user_id,education_level'])
            ->get()
            ->filter(function (User $user) use ($announcement) {
                if (!$announcement->level) {
                    return true;
                }

                $level = $user->role === User::ROLE_STAFF
                    ? $user->staffProfile?->education_level
                    : $user->studentProfile?->education_level;

                return strtolower(trim((string) $level)) === strtolower(trim((string) $announcement->level));
            });

        self::createForUsers(
            $school,
            $recipients,
            'announcement',
            $announcement->title,
            self::truncate($announcement->message),
            null,
            ['announcement_id' => (int) $announcement->id]
        );
    }

    public static function resultsPublished(School $school): void
    {
        $recipients = User::query()
            ->where('school_id', $school->id)
            ->where('role', User::ROLE_STUDENT)
            ->where('is_active', true)
            ->get();

        self::createForUsers(
            $school,
            $recipients,
            'result_published',
            'Results are available',
            'Your school has published the current results. Open your results to view them.',
            '/student/results',
            []
        );
    }

    private static function createForUsers(School $school, Collection $users, string $type, string $title, string $message, ?string $url, array $data): void
    {
        foreach ($users as $user) {
            $recipientUrl = $url;
            if ($type === 'announcement') {
                $recipientUrl = $user->role === User::ROLE_STAFF
                    ? '/staff/announcements'
                    : '/student/announcements';
            }

            $notification = PortalNotification::query()->create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $recipientUrl,
                'data' => $data,
            ]);
            SendPortalPushNotification::dispatch($notification->id);
        }
    }

    private static function truncate(string $message): string
    {
        return mb_strimwidth(trim($message), 0, 180, '...');
    }
}