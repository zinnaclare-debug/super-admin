<?php

namespace App\Support;

final class ClassicResultComponents
{
    public static function normalize(mixed $value): array
    {
        $source = is_array($value) ? $value : [];
        $psychomotor = is_array($source['psychomotor'] ?? null) ? $source['psychomotor'] : [];

        return [
            'psychomotor' => array_map(
                fn (int $index) => max(0, min(2, (int) ($psychomotor[$index] ?? 0))),
                range(0, 4)
            ),
            'quiz' => max(0, min(5, (int) ($source['quiz'] ?? 0))),
            'note_copying' => max(0, min(5, (int) ($source['note_copying'] ?? 0))),
        ];
    }
}
