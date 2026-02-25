<?php

namespace App\Support;

use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\LevelDepartment;
use App\Models\SchoolClass;

class DepartmentTemplateSync
{
    private const ALLOWED_LEVELS = ['nursery', 'primary', 'secondary'];

    public static function normalizeLevels(mixed $rawLevels): array
    {
        if (!is_array($rawLevels)) {
            return self::ALLOWED_LEVELS;
        }

        $levels = collect($rawLevels)
            ->map(function ($item) {
                $level = is_array($item) ? ($item['level'] ?? null) : $item;
                $level = strtolower(trim((string) $level));
                return in_array($level, self::ALLOWED_LEVELS, true) ? $level : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return !empty($levels) ? $levels : self::ALLOWED_LEVELS;
    }

    public static function normalizeTemplateNames(mixed $rawTemplates): array
    {
        if (!is_array($rawTemplates)) {
            return [];
        }

        return collect($rawTemplates)
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique(fn ($name) => strtolower($name))
            ->values()
            ->all();
    }

    public static function syncTemplateToAllSessions(int $schoolId, string $departmentName): void
    {
        $departmentName = trim($departmentName);
        if ($departmentName === '') {
            return;
        }

        $sessions = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->get(['id', 'levels']);

        foreach ($sessions as $session) {
            $levels = self::normalizeLevels($session->levels);
            self::syncTemplateToSession($schoolId, (int) $session->id, $levels, $departmentName);
        }
    }

    public static function syncTemplatesToSession(
        int $schoolId,
        int $sessionId,
        array $levels,
        array $departmentNames
    ): void {
        $normalizedNames = self::normalizeTemplateNames($departmentNames);
        if (empty($normalizedNames)) {
            return;
        }

        $normalizedLevels = self::normalizeLevels($levels);

        foreach ($normalizedNames as $departmentName) {
            self::syncTemplateToSession($schoolId, $sessionId, $normalizedLevels, $departmentName);
        }
    }

    public static function syncTemplateToSession(
        int $schoolId,
        int $sessionId,
        array $levels,
        string $departmentName
    ): void {
        $departmentName = trim($departmentName);
        if ($departmentName === '') {
            return;
        }

        $normalizedLevels = self::normalizeLevels($levels);

        foreach ($normalizedLevels as $level) {
            LevelDepartment::query()->firstOrCreate([
                'school_id' => $schoolId,
                'academic_session_id' => $sessionId,
                'level' => $level,
                'name' => $departmentName,
            ]);
        }

        $classes = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('level', $normalizedLevels)
            ->get(['id']);

        foreach ($classes as $class) {
            ClassDepartment::query()->firstOrCreate([
                'school_id' => $schoolId,
                'class_id' => (int) $class->id,
                'name' => $departmentName,
            ]);
        }
    }
}

