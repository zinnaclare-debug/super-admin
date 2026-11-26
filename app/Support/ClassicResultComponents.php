<?php

namespace App\Support;

final class ClassicResultComponents
{
    public static function defaults(?array $schema = null): array
    {
        $sourceSchema = is_array($schema) ? $schema : [];
        if (($sourceSchema['layout'] ?? null) !== 'classic') {
            return ['psychomotor' => [0, 0, 0, 0, 0], 'quiz' => 0, 'note_copying' => 0];
        }

        return [
            'psychomotor' => array_map(
                fn (int $index) => max(0, min(100, (int) ($sourceSchema['psychomotor_maxes'][$index] ?? 0))),
                range(0, 4)
            ),
            'quiz' => max(0, min(100, (int) ($sourceSchema['quiz_max'] ?? 0))),
            'note_copying' => max(0, min(100, (int) ($sourceSchema['note_copying_max'] ?? 0))),
        ];
    }

    public static function normalize(mixed $value, ?array $schema = null): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;
        $source = is_array($value) ? $value : (is_array($decoded) ? $decoded : []);
        $psychomotor = is_array($source['psychomotor'] ?? null) ? $source['psychomotor'] : [];
        $sourceSchema = is_array($schema) ? $schema : [];
        $defaults = self::defaults($sourceSchema);
        $hasSavedComponents = is_array($source['psychomotor'] ?? null)
            || array_key_exists('quiz', $source)
            || array_key_exists('note_copying', $source);
        $isInitialized = filter_var($source['_initialized'] ?? false, FILTER_VALIDATE_BOOL);

        // The former fixed-score release wrote zero-value component objects for
        // some students. They were never editable, so treat only those legacy,
        // uninitialised objects as the configured defaults. New staff edits carry
        // _initialized, which preserves intentional zero scores.
        $legacyEmpty = $hasSavedComponents
            && !$isInitialized
            && array_sum(array_map(fn ($value) => (int) $value, $psychomotor)) === 0
            && (int) ($source['quiz'] ?? 0) === 0
            && (int) ($source['note_copying'] ?? 0) === 0;
        if (($sourceSchema['layout'] ?? null) === 'classic' && (!$hasSavedComponents || $legacyEmpty)) {
            return [...$defaults, '_initialized' => false];
        }

        $configuredMaxes = is_array($sourceSchema['psychomotor_maxes'] ?? null)
            ? $sourceSchema['psychomotor_maxes']
            : [0, 0, 0, 0, 0];
        $psychomotorMaxes = [];

        for ($index = 0; $index < 5; $index++) {
            $psychomotorMaxes[$index] = max(0, min(100, (int) ($configuredMaxes[$index] ?? 0)));
        }

        $quizMax = max(0, min(100, (int) ($sourceSchema['quiz_max'] ?? 5)));
        $noteCopyingMax = max(0, min(100, (int) ($sourceSchema['note_copying_max'] ?? 5)));

        return [
            'psychomotor' => array_map(
                fn (int $index) => max(0, min($psychomotorMaxes[$index], (int) ($psychomotor[$index] ?? 0))),
                range(0, 4)
            ),
            'quiz' => max(0, min($quizMax, (int) ($source['quiz'] ?? 0))),
            'note_copying' => max(0, min($noteCopyingMax, (int) ($source['note_copying'] ?? 0))),
            '_initialized' => $isInitialized,
        ];
    }

    public static function forStorage(mixed $value, ?array $schema = null): array
    {
        return [...self::normalize($value, $schema), '_initialized' => true];
    }
}
