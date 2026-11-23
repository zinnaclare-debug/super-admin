<?php

namespace App\Support;

final class ClassicResultComponents
{
    public static function normalize(mixed $value, ?array $schema = null): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;
        $source = is_array($value) ? $value : (is_array($decoded) ? $decoded : []);

        // New Classic Full records receive this fixed 20-mark enrichment component.
        // Existing saved values remain intact when staff later amend only CA or Exam.
        if (($schema['layout'] ?? null) === 'classic'
            && filter_var($schema['classic_fixed_components'] ?? false, FILTER_VALIDATE_BOOL)) {
            $hasSavedComponents = is_array($source['psychomotor'] ?? null)
                || array_key_exists('quiz', $source)
                || array_key_exists('note_copying', $source);
            if (!$hasSavedComponents) {
                return [
                    'psychomotor' => [2, 2, 2, 2, 2],
                    'quiz' => 5,
                    'note_copying' => 5,
                ];
            }

            $psychomotor = is_array($source['psychomotor'] ?? null) ? $source['psychomotor'] : [];
            return [
                'psychomotor' => array_map(fn (int $index) => max(0, min(2, (int) ($psychomotor[$index] ?? 0))), range(0, 4)),
                'quiz' => max(0, min(5, (int) ($source['quiz'] ?? 0))),
                'note_copying' => max(0, min(5, (int) ($source['note_copying'] ?? 0))),
            ];
        }
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
