<?php

namespace App\Support;

class ClassTemplateSchema
{
    private const SECTION_SIZES = [
        'pre_nursery' => 3,
        'nursery' => 3,
        'primary' => 6,
        'secondary' => 6,
    ];

    private const SECTION_LABELS = [
        'pre_nursery' => 'Pre Nursery',
        'nursery' => 'Nursery',
        'primary' => 'Primary',
        'secondary' => 'Secondary',
    ];

    public static function defaults(): array
    {
        $sections = [];
        foreach (self::SECTION_SIZES as $key => $count) {
            $label = self::SECTION_LABELS[$key];
            $sections[] = [
                'key' => $key,
                'label' => $label,
                'enabled' => $key !== 'pre_nursery',
                'classes' => self::defaultClassRows($label, $count),
            ];
        }

        return $sections;
    }

    public static function normalize(mixed $raw): array
    {
        $incoming = is_array($raw) ? $raw : [];
        $incomingByKey = [];
        foreach ($incoming as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rawKey = (string) ($item['key'] ?? '');
            $key = self::normalizeKey($rawKey);
            if (!array_key_exists($key, self::SECTION_SIZES)) {
                continue;
            }

            $incomingByKey[$key] = $item;
        }

        $normalized = [];
        foreach (self::SECTION_SIZES as $key => $size) {
            $item = $incomingByKey[$key] ?? [];
            $defaultLabel = self::SECTION_LABELS[$key];
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                $label = $defaultLabel;
            }

            $enabled = array_key_exists('enabled', $item)
                ? (bool) $item['enabled']
                : ($key !== 'pre_nursery');

            $classes = [];
            if (isset($item['classes']) && is_array($item['classes'])) {
                foreach ($item['classes'] as $classRow) {
                    $normalizedClassRow = self::normalizeClassRow($classRow);
                    if ($normalizedClassRow === null) {
                        continue;
                    }
                    $classes[] = $normalizedClassRow;
                }
            }

            $classes = collect($classes)
                ->unique(fn (array $row) => strtolower((string) ($row['name'] ?? '')))
                ->values()
                ->all();

            if (count($classes) > $size) {
                $classes = array_slice($classes, 0, $size);
            }
            if (count($classes) < $size) {
                $defaults = self::defaultClassRows($label, $size);
                $existing = collect($classes)
                    ->map(fn (array $row) => strtolower((string) ($row['name'] ?? '')))
                    ->all();

                foreach ($defaults as $defaultRow) {
                    if (count($classes) >= $size) {
                        break;
                    }

                    $defaultName = strtolower((string) ($defaultRow['name'] ?? ''));
                    if (!in_array($defaultName, $existing, true)) {
                        $classes[] = $defaultRow;
                        $existing[] = $defaultName;
                    }
                }
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'enabled' => $enabled,
                'classes' => array_values($classes),
            ];
        }

        return $normalized;
    }

    public static function activeSections(array $templates): array
    {
        return array_values(array_filter(self::normalize($templates), function (array $section) {
            return (bool) ($section['enabled'] ?? false);
        }));
    }

    public static function activeLevelKeys(array $templates): array
    {
        return array_values(array_map(
            fn (array $section) => (string) $section['key'],
            self::activeSections($templates)
        ));
    }

    public static function sectionClassNames(array $section, bool $enabledOnly = true): array
    {
        $classes = is_array($section['classes'] ?? null) ? $section['classes'] : [];

        return collect($classes)
            ->map(function ($classRow) {
                $normalized = self::normalizeClassRow($classRow);
                if ($normalized === null) {
                    return null;
                }

                return [
                    'name' => (string) $normalized['name'],
                    'enabled' => (bool) ($normalized['enabled'] ?? true),
                ];
            })
            ->filter()
            ->filter(fn (array $row) => !$enabledOnly || (bool) $row['enabled'])
            ->map(fn (array $row) => trim((string) $row['name']))
            ->filter(fn (string $name) => $name !== '')
            ->unique(fn (string $name) => strtolower($name))
            ->values()
            ->all();
    }

    public static function activeClassNames(array $section): array
    {
        return self::sectionClassNames($section, true);
    }

    public static function sectionSize(string $key): int
    {
        return self::SECTION_SIZES[$key] ?? 0;
    }

    private static function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        return trim($value, '_');
    }

    private static function normalizeClassRow(mixed $value): ?array
    {
        if (is_array($value)) {
            $name = trim((string) ($value['name'] ?? ''));
            if ($name === '') {
                return null;
            }

            $enabled = array_key_exists('enabled', $value)
                ? (bool) $value['enabled']
                : true;

            return [
                'name' => $name,
                'enabled' => $enabled,
            ];
        }

        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'enabled' => true,
        ];
    }

    private static function defaultClassRows(string $label, int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'name' => trim($label) . ' ' . $i,
                'enabled' => true,
            ];
        }
        return $rows;
    }
}
