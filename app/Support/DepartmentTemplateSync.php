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
        $classMap = self::normalizeClassTemplateMap($rawTemplates);
        $classNames = self::flattenClassTemplateNames($classMap);
        if (!empty($classNames)) {
            return $classNames;
        }

        $levelMap = self::normalizeLevelTemplateMap($rawTemplates);
        return self::flattenLevelTemplateNames($levelMap);
    }

    public static function normalizeLevelTemplateMap(mixed $rawTemplates): array
    {
        $empty = self::emptyLevelTemplateMap();
        if (!is_array($rawTemplates)) {
            return $empty;
        }

        if (
            array_key_exists('by_class', $rawTemplates)
            || self::isListOfClassRows($rawTemplates)
        ) {
            $classMap = self::normalizeClassTemplateMap($rawTemplates);
            foreach (self::ALLOWED_LEVELS as $level) {
                $rows = is_array($classMap[$level] ?? null) ? $classMap[$level] : [];
                $names = collect($rows)
                    ->flatMap(function ($row) {
                        if (!is_array($row) || !($row['enabled'] ?? false)) {
                            return [];
                        }

                        return self::normalizeNameList($row['names'] ?? []);
                    })
                    ->unique(fn ($name) => strtolower((string) $name))
                    ->values()
                    ->all();

                $empty[$level] = [
                    'enabled' => !empty($names),
                    'names' => $names,
                ];
            }

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
        } elseif (self::isAssociativeLevelMap($rawTemplates)) {
            $byLevel = $rawTemplates;
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

    public static function emptyClassTemplateMap(array $classTemplates = []): array
    {
        $map = [];
        $templates = ClassTemplateSchema::normalize($classTemplates);
        foreach ($templates as $section) {
            $level = strtolower(trim((string) ($section['key'] ?? '')));
            if ($level === '') {
                continue;
            }

            if (!array_key_exists($level, $map)) {
                $map[$level] = [];
            }

            $classes = is_array($section['classes'] ?? null) ? $section['classes'] : [];
            foreach ($classes as $classRow) {
                $name = trim((string) (is_array($classRow) ? ($classRow['name'] ?? '') : $classRow));
                if ($name === '') {
                    continue;
                }

                if (!array_key_exists($name, $map[$level])) {
                    $map[$level][$name] = [
                        'enabled' => false,
                        'names' => [],
                    ];
                }
            }
        }

        return $map;
    }

    public static function normalizeClassTemplateMap(mixed $rawTemplates, array $classTemplates = []): array
    {
        $map = self::emptyClassTemplateMap($classTemplates);
        if (!is_array($rawTemplates)) {
            return $map;
        }

        $globalNames = [];
        $levelMap = self::emptyLevelTemplateMap();
        $rawByClass = [];

        if (array_key_exists('global', $rawTemplates)) {
            $globalNames = self::normalizeNameList($rawTemplates['global']);
        }

        if (array_key_exists('by_level', $rawTemplates) && is_array($rawTemplates['by_level'])) {
            $levelMap = self::normalizeLevelTemplateMap(['by_level' => $rawTemplates['by_level']]);
        } elseif (self::isAssociativeLevelMap($rawTemplates) || self::isListOfLevelRows($rawTemplates)) {
            $levelMap = self::normalizeLevelTemplateMap($rawTemplates);
        }

        if (array_key_exists('by_class', $rawTemplates)) {
            $rawByClass = self::extractClassRows($rawTemplates['by_class']);
        } elseif (self::isListOfClassRows($rawTemplates)) {
            $rawByClass = self::extractClassRows($rawTemplates);
        } elseif (empty($globalNames) && self::isTemplateNameList($rawTemplates)) {
            // Legacy format: ['Gold', 'Diamond'] applies to all classes.
            $globalNames = self::normalizeNameList($rawTemplates);
        }

        foreach ($rawByClass as $level => $classRows) {
            if (!array_key_exists($level, $map)) {
                $map[$level] = [];
            }

            foreach ($classRows as $className => $row) {
                if (!array_key_exists($className, $map[$level])) {
                    $map[$level][$className] = [
                        'enabled' => false,
                        'names' => [],
                    ];
                }
            }
        }

        foreach ($map as $level => $classRows) {
            $levelFallback = self::normalizeTemplateRow($levelMap[$level] ?? []);
            foreach ($classRows as $className => $existingRow) {
                $resolved = [
                    'enabled' => false,
                    'names' => [],
                ];

                if (!empty($globalNames)) {
                    $resolved = [
                        'enabled' => true,
                        'names' => $globalNames,
                    ];
                }

                if ($levelFallback['enabled']) {
                    $resolved = $levelFallback;
                }

                $rawClassRow = self::findClassRow($rawByClass[$level] ?? [], $className);
                if ($rawClassRow !== null) {
                    $resolved = self::normalizeTemplateRow($rawClassRow);
                }

                $map[$level][$className] = $resolved;
            }
        }

        return $map;
    }

    public static function serializeClassTemplateMap(array $map, array $classTemplates = []): array
    {
        $normalized = self::normalizeClassTemplateMap(['by_class' => $map], $classTemplates);
        return [
            'by_class' => $normalized,
            'by_level' => self::normalizeLevelTemplateMap(['by_class' => $normalized]),
            'global' => self::flattenClassTemplateNames($normalized),
        ];
    }

    public static function flattenClassTemplateNames(array $map): array
    {
        return collect($map)
            ->flatMap(function ($classRows) {
                if (!is_array($classRows)) {
                    return [];
                }

                return collect($classRows)
                    ->flatMap(function ($row) {
                        if (!is_array($row) || !($row['enabled'] ?? false)) {
                            return [];
                        }
                        return self::normalizeNameList($row['names'] ?? []);
                    })
                    ->all();
            })
            ->unique(fn ($name) => strtolower((string) $name))
            ->values()
            ->all();
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

    public static function syncClassTemplatesToSession(
        int $schoolId,
        int $sessionId,
        array $classTemplates,
        mixed $rawTemplates
    ): void {
        $normalizedTemplates = ClassTemplateSchema::normalize($classTemplates);
        $activeSections = ClassTemplateSchema::activeSections($normalizedTemplates);
        if (empty($activeSections)) {
            return;
        }

        $classMap = self::normalizeClassTemplateMap($rawTemplates, $normalizedTemplates);
        $classes = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->get(['id', 'level', 'name']);

        $classesByKey = [];
        foreach ($classes as $class) {
            $key = strtolower(trim((string) $class->level)) . '|' . strtolower(trim((string) $class->name));
            $classesByKey[$key] = (int) $class->id;
        }

        foreach ($activeSections as $section) {
            $level = strtolower(trim((string) ($section['key'] ?? '')));
            if ($level === '') {
                continue;
            }

            $classNames = ClassTemplateSchema::activeClassNames($section);
            foreach ($classNames as $className) {
                $row = self::findClassRow($classMap[$level] ?? [], $className);
                $resolved = self::normalizeTemplateRow($row ?? []);
                if (!$resolved['enabled']) {
                    continue;
                }

                $classKey = $level . '|' . strtolower(trim((string) $className));
                $classId = $classesByKey[$classKey] ?? null;

                foreach ($resolved['names'] as $departmentName) {
                    LevelDepartment::query()->firstOrCreate([
                        'school_id' => $schoolId,
                        'academic_session_id' => $sessionId,
                        'level' => $level,
                        'name' => $departmentName,
                    ]);

                    if ($classId) {
                        ClassDepartment::query()->firstOrCreate([
                            'school_id' => $schoolId,
                            'class_id' => (int) $classId,
                            'name' => $departmentName,
                        ]);
                    }
                }
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
        if (self::isArrayList($rawTemplates) === false) {
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

    private static function isListOfClassRows(array $rawTemplates): bool
    {
        if (self::isArrayList($rawTemplates) === false) {
            return false;
        }

        foreach ($rawTemplates as $row) {
            if (!is_array($row)) {
                return false;
            }
            if (!array_key_exists('level', $row) || !array_key_exists('class_name', $row)) {
                return false;
            }
        }

        return true;
    }

    private static function isAssociativeLevelMap(array $rawTemplates): bool
    {
        if (self::isArrayList($rawTemplates)) {
            return false;
        }

        foreach (array_keys($rawTemplates) as $key) {
            $normalized = strtolower(trim((string) $key));
            if (in_array($normalized, self::ALLOWED_LEVELS, true)) {
                return true;
            }
        }

        return false;
    }

    private static function isTemplateNameList(array $rawTemplates): bool
    {
        if (self::isArrayList($rawTemplates) === false) {
            return false;
        }

        foreach ($rawTemplates as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return true;
    }

    private static function extractClassRows(mixed $rawByClass): array
    {
        $map = [];
        if (!is_array($rawByClass)) {
            return $map;
        }

        if (self::isListOfClassRows($rawByClass)) {
            foreach ($rawByClass as $row) {
                $level = strtolower(trim((string) ($row['level'] ?? '')));
                $className = trim((string) ($row['class_name'] ?? ''));
                if ($level === '' || $className === '') {
                    continue;
                }

                if (!array_key_exists($level, $map)) {
                    $map[$level] = [];
                }

                $map[$level][$className] = self::normalizeTemplateRow([
                    'enabled' => (bool) ($row['enabled'] ?? false),
                    'names' => $row['names'] ?? [],
                ]);
            }

            return $map;
        }

        foreach ($rawByClass as $levelKey => $rows) {
            $level = strtolower(trim((string) $levelKey));
            if ($level === '' || !is_array($rows)) {
                continue;
            }

            if (!array_key_exists($level, $map)) {
                $map[$level] = [];
            }

            foreach ($rows as $classKey => $row) {
                $className = trim((string) $classKey);
                if (is_array($row) && array_key_exists('class_name', $row)) {
                    $className = trim((string) ($row['class_name'] ?? $className));
                }
                if ($className === '') {
                    continue;
                }

                $sourceRow = is_array($row)
                    ? [
                        'enabled' => (bool) ($row['enabled'] ?? false),
                        'names' => $row['names'] ?? [],
                    ]
                    : [
                        'enabled' => false,
                        'names' => [],
                    ];

                $map[$level][$className] = self::normalizeTemplateRow($sourceRow);
            }
        }

        return $map;
    }

    private static function findClassRow(array $rows, string $className): ?array
    {
        $needle = strtolower(trim($className));
        if ($needle === '') {
            return null;
        }

        foreach ($rows as $name => $row) {
            if (strtolower(trim((string) $name)) !== $needle) {
                continue;
            }

            return is_array($row) ? $row : null;
        }

        return null;
    }

    private static function normalizeTemplateRow(mixed $rawRow): array
    {
        if (!is_array($rawRow)) {
            return [
                'enabled' => false,
                'names' => [],
            ];
        }

        $names = self::normalizeNameList($rawRow['names'] ?? []);
        $enabled = (bool) ($rawRow['enabled'] ?? false);

        return [
            'enabled' => $enabled && !empty($names),
            'names' => $names,
        ];
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

    private static function isArrayList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        $expectedKey = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }

        return true;
    }
}
