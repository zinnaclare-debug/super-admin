<?php

namespace App\Support;

class AssessmentSchema
{
    public static function default(): array
    {
        return [
            'ca_maxes' => [30, 0, 0, 0, 0],
            'ca_labels' => ['CA1', 'CA2', 'CA3', 'CA4', 'CA5'],
            'exam_max' => 70,
            'total_max' => 100,
        ];
    }

    public static function normalizeSchema(mixed $raw): array
    {
        $base = self::default();

        $schema = is_array($raw) ? $raw : [];
        if (isset($schema['default']) && is_array($schema['default'])) {
            $schema = $schema['default'];
        }

        $caMaxes = isset($schema['ca_maxes']) && is_array($schema['ca_maxes'])
            ? $schema['ca_maxes']
            : $base['ca_maxes'];
        $caLabels = isset($schema['ca_labels']) && is_array($schema['ca_labels'])
            ? $schema['ca_labels']
            : $base['ca_labels'];

        $normalized = [];
        $labels = [];
        for ($i = 0; $i < 5; $i++) {
            $normalized[$i] = max(0, (int) ($caMaxes[$i] ?? 0));
            $label = trim((string) ($caLabels[$i] ?? ''));
            $labels[$i] = $label !== '' ? substr($label, 0, 30) : 'CA' . ($i + 1);
        }

        $caTotalMax = array_sum($normalized);
        $examMax = isset($schema['exam_max']) ? (int) $schema['exam_max'] : (100 - $caTotalMax);
        $examMax = max(0, min(100, $examMax));

        if (($caTotalMax + $examMax) !== 100) {
            $examMax = max(0, 100 - $caTotalMax);
        }

        return [
            'ca_maxes' => $normalized,
            'ca_labels' => $labels,
            'exam_max' => $examMax,
            'total_max' => 100,
        ];
    }

    public static function normalizeLevelKey(?string $level): ?string
    {
        $normalized = strtolower(trim((string) $level));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: '';
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : null;
    }

    public static function normalizeLevelSchemas(mixed $raw, array $levels = []): array
    {
        $source = is_array($raw) ? $raw : [];
        $defaultSchema = self::normalizeSchema($source['default'] ?? $source);
        $byLevelSource = isset($source['by_level']) && is_array($source['by_level'])
            ? $source['by_level']
            : [];

        $normalizedLevels = [];
        foreach ($levels as $level) {
            $levelKey = self::normalizeLevelKey((string) $level);
            if ($levelKey !== null) {
                $normalizedLevels[] = $levelKey;
            }
        }
        $normalizedLevels = array_values(array_unique($normalizedLevels));

        if (empty($normalizedLevels)) {
            $normalizedLevels = array_values(array_unique(array_filter(array_map(
                fn ($level) => self::normalizeLevelKey((string) $level),
                array_keys($byLevelSource)
            ))));
        }

        $byLevel = [];
        foreach ($normalizedLevels as $levelKey) {
            $levelSchema = null;
            foreach ($byLevelSource as $rawKey => $rawSchema) {
                if (self::normalizeLevelKey((string) $rawKey) === $levelKey) {
                    $levelSchema = $rawSchema;
                    break;
                }
            }

            $byLevel[$levelKey] = self::normalizeSchema(is_array($levelSchema) ? $levelSchema : $defaultSchema);
        }

        return [
            'default' => $defaultSchema,
            'by_level' => $byLevel,
        ];
    }

    public static function schemaForLevel(mixed $raw, ?string $level): array
    {
        $normalized = self::normalizeLevelSchemas($raw);
        $levelKey = self::normalizeLevelKey($level);

        if ($levelKey !== null && isset($normalized['by_level'][$levelKey])) {
            return self::normalizeSchema($normalized['by_level'][$levelKey]);
        }

        return self::normalizeSchema($normalized['default'] ?? $raw);
    }

    public static function activeCaIndices(array $schema): array
    {
        $indices = [];
        $caMaxes = self::normalizeSchema($schema)['ca_maxes'];
        foreach ($caMaxes as $index => $max) {
            if ((int) $max > 0) {
                $indices[] = (int) $index;
            }
        }

        if (empty($indices)) {
            $indices[] = 0;
        }

        return $indices;
    }

    public static function normalizeBreakdown(mixed $rawBreakdown, array $schema, int $legacyCa = 0): array
    {
        $normalizedSchema = self::normalizeSchema($schema);
        $caMaxes = $normalizedSchema['ca_maxes'];

        $source = [];
        if (is_string($rawBreakdown)) {
            $decoded = json_decode($rawBreakdown, true);
            if (is_array($decoded)) {
                $source = $decoded;
            }
        } elseif (is_array($rawBreakdown)) {
            $source = $rawBreakdown;
        }

        $breakdown = [];
        for ($i = 0; $i < 5; $i++) {
            $value = 0;

            if (array_key_exists($i, $source)) {
                $value = (int) $source[$i];
            } elseif (array_key_exists('ca' . ($i + 1), $source)) {
                $value = (int) $source['ca' . ($i + 1)];
            }

            $breakdown[$i] = max(0, min((int) $caMaxes[$i], $value));
        }

        if (array_sum($breakdown) === 0 && $legacyCa > 0) {
            $remaining = max(0, $legacyCa);
            for ($i = 0; $i < 5; $i++) {
                if ($remaining <= 0) {
                    break;
                }
                $slot = min((int) $caMaxes[$i], $remaining);
                $breakdown[$i] = $slot;
                $remaining -= $slot;
            }
        }

        return $breakdown;
    }

    public static function breakdownTotal(array $breakdown): int
    {
        return (int) array_sum(array_map(fn ($v) => (int) $v, $breakdown));
    }

    public static function formatBreakdown(array $breakdown, array $schema): string
    {
        $parts = [];
        $normalizedSchema = self::normalizeSchema($schema);
        foreach (self::activeCaIndices($normalizedSchema) as $index) {
            $score = (int) ($breakdown[$index] ?? 0);
            $max = (int) ($normalizedSchema['ca_maxes'][$index] ?? 0);
            $label = (string) ($normalizedSchema['ca_labels'][$index] ?? ('CA' . ($index + 1)));
            $parts[] = $label . ': ' . $score . '/' . $max;
        }

        return implode(', ', $parts);
    }
}
