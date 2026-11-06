<?php

namespace App\Support;

final class ClassicResultComponents
{
    public static function normalize(mixed $value, ?array $schema = null): array
    {
        $source = is_array($value) ? $value : [];
        $psychomotor = is_array($source['psychomotor'] ?? null) ? $source['psychomotor'] : [];
        $sourceSchema = is_array($schema) ? $schema : [];
        $configuredMaxes = is_array($sourceSchema['psychomotor_maxes'] ?? null)
            ? $sourceSchema['psychomotor_maxes']
            : [2, 2, 2, 2, 2];
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
        ];
    }
}