<?php

namespace App\Support;

use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\LevelDepartment;
use App\Models\SchoolClass;

class DepartmentTemplateSync
{
    private const ALLOWED_LEVELS = ['pre_nursery', 'nursery', 'primary', 'secondary'];

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
        $levelMap = self::normalizeLevelTemplateMap($rawTemplates);
        return self::flattenLevelTemplateNames($levelMap);
    }

    public static function normalizeLevelTemplateMap(mixed $rawTemplates): array
    {
        $empty = self::emptyLevelTemplateMap();
        if (!is_array($rawTemplates)) {
            return $empty;
        }

        $globalNames = [];
        $byLevel = null;

        // New format: ['by_level' => ['primary' => ['enabled' => true, 'names' => ['Gold']]]]
        if (array_key_exists('by_level', $rawTemplates) && is_array($rawTemplates['by_level'])) {
            $byLevel = $rawTemplates['by_level'];
            if (array_key_exists('global', $rawTemplates)) {
                $globalNames = self::normalizeNameList($rawTemplates['global']);
            }
        } elseif (self::isListOfLevelRows($rawTemplates)) {
            // Alternate format: [ ['level' => 'primary', 'enabled' => true, 'names' => ['Gold']] ]
            $byLevel = [];
            foreach ($rawTemplates as $row) {
                $level = strtolower(trim((string) ($row['level'] ?? '')));
                if (!in_array($level, self::ALLOWED_LEVELS, true)) {
                    continue;
                }
                $byLevel[$level] = [
                    'enabled' => (bool) ($row['enabled'] ?? false),
                    'names' => self::normalizeNameList($row['names'] ?? []),
                ];
            }
        } else {
            // Legacy format: ['Gold', 'Diamond'] => applies to all levels.
            $globalNames = self::normalizeNameList($rawTemplates);
        }

        foreach (self::ALLOWED_LEVELS as $level) {
            $row = is_array($byLevel) && array_key_exists($level, $byLevel)
                ? $byLevel[$level]
                : [];

            $enabled = (bool) ($row['enabled'] ?? false);
            $names = self::normalizeNameList($row['names'] ?? []);

            if (empty($names) && !empty($globalNames)) {
                $names = $globalNames;
                $enabled = true;
            }

            $empty[$level] = [
                'enabled' => $enabled && !empty($names),
                'names' => $names,
            ];
        }

        return $empty;
    }

    public static function emptyLevelTemplateMap(): array
    {
        $rows = [];
        foreach (self::ALLOWED_LEVELS as $level) {
            $rows[$level] = [
                'enabled' => false,
                'names' => [],
            ];
        }
        return $rows;
    }

    public static function serializeLevelTemplateMap(array $map): array
    {
        $normalized = self::normalizeLevelTemplateMap(['by_level' => $map]);
        return [
            'by_level' => $normalized,
            'global' => self::flattenLevelTemplateNames($normalized),
        ];
    }

    public static function flattenLevelTemplateNames(array $map): array
    {
        return collect($map)
            ->flatMap(function ($row) {
                if (!is_array($row) || !($row['enabled'] ?? false)) {
                    return [];
                }
                return self::normalizeNameList($row['names'] ?? []);
            })
            ->unique(fn ($name) => strtolower((string) $name))
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

    public static function syncLevelTemplatesToSession(
        int $schoolId,
        int $sessionId,
        array $levels,
        array $levelTemplateMap
    ): void {
        $normalizedLevels = self::normalizeLevels($levels);
        $normalizedMap = self::normalizeLevelTemplateMap(['by_level' => $levelTemplateMap]);

        foreach ($normalizedLevels as $level) {
            $row = $normalizedMap[$level] ?? ['enabled' => false, 'names' => []];
            if (!($row['enabled'] ?? false)) {
                continue;
            }

            $names = self::normalizeNameList($row['names'] ?? []);
            if (empty($names)) {
                continue;
            }

            foreach ($names as $departmentName) {
                self::syncTemplateToSession($schoolId, $sessionId, [$level], $departmentName);
            }
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

    private static function isListOfLevelRows(array $rawTemplates): bool
    {
        if (array_is_list($rawTemplates) === false) {
            return false;
        }

        foreach ($rawTemplates as $row) {
            if (!is_array($row)) {
                return false;
            }
            if (!array_key_exists('level', $row)) {
                return false;
            }
        }

        return true;
    }

    private static function normalizeNameList(mixed $rawNames): array
    {
        if (!is_array($rawNames)) {
            return [];
        }

        return collect($rawNames)
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique(fn ($name) => strtolower($name))
            ->values()
            ->all();
    }
}
