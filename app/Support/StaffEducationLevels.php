<?php

namespace App\Support;

use App\Models\Staff;

class StaffEducationLevels
{
    /**
     * Old staff records retain their original single level until an admin adds more.
     */
    public static function normalize(mixed $levels, ?string $fallbackLevel = null): array
    {
        if (is_string($levels)) {
            $decoded = json_decode($levels, true);
            $levels = is_array($decoded) ? $decoded : [$levels];
        }

        $source = is_array($levels) ? $levels : [];
        if (empty($source) && filled($fallbackLevel)) {
            $source = [$fallbackLevel];
        }

        return collect($source)
            ->map(fn ($level) => strtolower(trim((string) $level)))
            ->filter(fn ($level) => $level !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function forStaff(?Staff $staff): array
    {
        return $staff ? self::normalize($staff->education_levels, $staff->education_level) : [];
    }

    public static function supports(?Staff $staff, ?string $level): bool
    {
        $level = strtolower(trim((string) $level));

        return $level !== '' && in_array($level, self::forStaff($staff), true);
    }
}