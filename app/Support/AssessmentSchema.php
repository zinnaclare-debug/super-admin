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
            'layout' => 'compact',
            'profile_version' => 0,
            'psychomotor_maxes' => [0, 0, 0, 0, 0],
            'psychomotor_labels' => ['P1', 'P2', 'P3', 'P4', 'P5'],
            'quiz_max' => 0,
            'quiz_label' => 'Quiz',
            'note_copying_max' => 0,
            'note_copying_label' => 'Note Copying',
            'comments_only' => false,
        ];
    }

    public static function normalizeSchema(mixed $raw): array
    {
        $base = self::default();
        $schema = is_array($raw) ? $raw : [];
        if (isset($schema['default']) && is_array($schema['default'])) {
            $schema = $schema['default'];
        }

        $layout = strtolower(trim((string) ($schema['layout'] ?? 'compact')));
        $layout = $layout === 'classic' ? 'classic' : 'compact';
        $isClassic = $layout === 'classic';
        $caMaxes = is_array($schema['ca_maxes'] ?? null) ? $schema['ca_maxes'] : $base['ca_maxes'];
        $caLabels = is_array($schema['ca_labels'] ?? null) ? $schema['ca_labels'] : $base['ca_labels'];
        $psychomotorMaxes = is_array($schema['psychomotor_maxes'] ?? null) ? $schema['psychomotor_maxes'] : ($isClassic ? [2, 2, 2, 2, 2] : [0, 0, 0, 0, 0]);
        $psychomotorLabels = is_array($schema['psychomotor_labels'] ?? null) ? $schema['psychomotor_labels'] : $base['psychomotor_labels'];
        $normalizedCa = [];
        $labels = [];
        $normalizedPsychomotor = [];
        $normalizedPsychomotorLabels = [];

        for ($index = 0; $index < 5; $index++) {
            $normalizedCa[$index] = max(0, min(100, (int) ($caMaxes[$index] ?? 0)));
            $label = trim((string) ($caLabels[$index] ?? ''));
            $labels[$index] = $label !== '' ? substr($label, 0, 30) : 'CA' . ($index + 1);
            $normalizedPsychomotor[$index] = $isClassic ? max(0, min(100, (int) ($psychomotorMaxes[$index] ?? 0))) : 0;
            $psychomotorLabel = trim((string) ($psychomotorLabels[$index] ?? ''));
            $normalizedPsychomotorLabels[$index] = $psychomotorLabel !== '' ? substr($psychomotorLabel, 0, 30) : 'P' . ($index + 1);
        }

        $quizMax = $isClassic ? max(0, min(100, (int) ($schema['quiz_max'] ?? 5))) : 0;
        $noteCopyingMax = $isClassic ? max(0, min(100, (int) ($schema['note_copying_max'] ?? 5))) : 0;
        $componentsTotalMax = array_sum($normalizedPsychomotor) + $quizMax + $noteCopyingMax;
        $numericTotalMax = max(0, 100 - $componentsTotalMax);
        $caTotalMax = array_sum($normalizedCa);
        $examMax = isset($schema['exam_max']) ? (int) $schema['exam_max'] : ($numericTotalMax - $caTotalMax);
        $examMax = max(0, min(100, $examMax));
        if (($caTotalMax + $examMax) !== $numericTotalMax) {
            $examMax = max(0, $numericTotalMax - $caTotalMax);
        }

        $quizLabel = trim((string) ($schema['quiz_label'] ?? $base['quiz_label']));
        $noteCopyingLabel = trim((string) ($schema['note_copying_label'] ?? $base['note_copying_label']));

        return [
            'ca_maxes' => $normalizedCa,
            'ca_labels' => $labels,
            'exam_max' => $examMax,
            'total_max' => 100,
            'layout' => $layout,
            'profile_version' => max(0, (int) ($schema['profile_version'] ?? 0)),
            'psychomotor_maxes' => $normalizedPsychomotor,
            'psychomotor_labels' => $normalizedPsychomotorLabels,
            'quiz_max' => $quizMax,
            'quiz_label' => $quizLabel !== '' ? substr($quizLabel, 0, 30) : 'Quiz',
            'note_copying_max' => $noteCopyingMax,
            'note_copying_label' => $noteCopyingLabel !== '' ? substr($noteCopyingLabel, 0, 30) : 'Note Copying',
            'components_total_max' => $componentsTotalMax,
            'comments_only' => filter_var($schema['comments_only'] ?? false, FILTER_VALIDATE_BOOL),
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

    public static function commentsOnlyForLevel(mixed $raw, ?string $level): bool
    {
        return (bool) (self::schemaForLevel($raw, $level)['comments_only'] ?? false);
    }

    public static function schemaForTemplateLevel(mixed $raw, ?string $level, mixed $templateRaw): array
    {
        $levelSchema = self::schemaForLevel($raw, $level);
        $template = ResultPdfTemplate::normalize($templateRaw);

        // Schools opt in when they save this versioned profile; legacy results retain their prior maths.
        if ((int) ($template['score_profile_version'] ?? 0) < 1) {
            return $levelSchema;
        }

        $profile = $template['score_profile'] ?? [];
        $profile['layout'] = ($template['layout'] ?? 'compact') === 'classic' ? 'classic' : 'compact';
        $profile['profile_version'] = 1;
        $profile['comments_only'] = (bool) ($levelSchema['comments_only'] ?? false);

        return self::normalizeSchema($profile);
    }

    public static function schemaForResult(mixed $savedProfile, array $fallback): array
    {
        if (is_array($savedProfile) && isset($savedProfile['ca_maxes'])) {
            return self::normalizeSchema($savedProfile);
        }

        return self::normalizeSchema($fallback);
    }

    public static function usesClassicComponents(array $schema): bool
    {
        return self::normalizeSchema($schema)['layout'] === 'classic';
    }

    public static function componentsTotal(mixed $rawComponents, array $schema): int
    {
        if (!self::usesClassicComponents($schema)) {
            return 0;
        }

        $components = ClassicResultComponents::normalize($rawComponents, $schema);

        return array_sum($components['psychomotor']) + $components['quiz'] + $components['note_copying'];
    }

    public static function totalFromScores(
        mixed $rawBreakdown,
        int $legacyCa,
        int $exam,
        mixed $rawComponents,
        array $schema
    ): int {
        $normalized = self::normalizeSchema($schema);
        $breakdown = self::normalizeBreakdown($rawBreakdown, $normalized, $legacyCa);
        $examScore = max(0, min((int) $normalized['exam_max'], $exam));

        return min(100, self::breakdownTotal($breakdown) + $examScore + self::componentsTotal($rawComponents, $normalized));
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
