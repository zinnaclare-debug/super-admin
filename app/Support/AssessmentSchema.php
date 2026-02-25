<?php

namespace App\Support;

class AssessmentSchema
{
    public static function default(): array
    {
        return [
            'ca_maxes' => [30, 0, 0, 0, 0],
            'exam_max' => 70,
            'total_max' => 100,
        ];
    }

    public static function normalizeSchema(mixed $raw): array
    {
        $base = self::default();

        $schema = is_array($raw) ? $raw : [];
        $caMaxes = isset($schema['ca_maxes']) && is_array($schema['ca_maxes'])
            ? $schema['ca_maxes']
            : $base['ca_maxes'];

        $normalized = [];
        for ($i = 0; $i < 5; $i++) {
            $normalized[$i] = max(0, (int) ($caMaxes[$i] ?? 0));
        }

        $caTotalMax = array_sum($normalized);
        $examMax = isset($schema['exam_max']) ? (int) $schema['exam_max'] : (100 - $caTotalMax);
        $examMax = max(0, min(100, $examMax));

        if (($caTotalMax + $examMax) !== 100) {
            $examMax = max(0, 100 - $caTotalMax);
        }

        return [
            'ca_maxes' => $normalized,
            'exam_max' => $examMax,
            'total_max' => 100,
        ];
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
            $parts[] = 'CA' . ($index + 1) . ': ' . $score . '/' . $max;
        }

        return implode(', ', $parts);
    }
}

