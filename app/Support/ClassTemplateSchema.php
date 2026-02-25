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
                'classes' => self::defaultClassNames($label, $count),
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
                foreach ($item['classes'] as $name) {
                    $clean = trim((string) $name);
                    if ($clean === '') {
                        continue;
                    }
                    $classes[] = $clean;
                }
            }
            $classes = array_values(array_unique($classes, SORT_STRING));
            if (count($classes) > $size) {
                $classes = array_slice($classes, 0, $size);
            }
            if (count($classes) < $size) {
                $defaults = self::defaultClassNames($label, $size);
                foreach ($defaults as $placeholder) {
                    if (count($classes) >= $size) {
                        break;
                    }
                    if (!in_array($placeholder, $classes, true)) {
                        $classes[] = $placeholder;
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

    private static function defaultClassNames(string $label, int $count): array
    {
        $names = [];
        for ($i = 1; $i <= $count; $i++) {
            $names[] = trim($label) . ' ' . $i;
        }
        return $names;
    }
}

