<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class CommentOnlySchema
{
    public const CRITERIA_COUNT = 15;

    public static function defaults(): array
    {
        return [
            'criteria' => array_map(
                fn (int $index) => [
                    'key' => 'learning_area_' . $index,
                    'label' => 'Learning Area ' . $index,
                ],
                range(1, self::CRITERIA_COUNT)
            ),
        ];
    }

    public static function normalize(mixed $value): array
    {
        $source = is_array($value) ? ($value['criteria'] ?? $value) : [];
        $criteria = [];

        for ($index = 0; $index < self::CRITERIA_COUNT; $index++) {
            $candidate = is_array($source) ? ($source[$index] ?? []) : [];
            $label = trim((string) (is_array($candidate) ? ($candidate['label'] ?? '') : $candidate));
            $criteria[] = [
                'key' => 'learning_area_' . ($index + 1),
                'label' => mb_substr($label !== '' ? $label : ('Learning Area ' . ($index + 1)), 0, 80),
            ];
        }

        return ['criteria' => $criteria];
    }

    public static function validateAndNormalize(mixed $value): array
    {
        $source = is_array($value) ? ($value['criteria'] ?? $value) : null;
        if (!is_array($source) || count($source) !== self::CRITERIA_COUNT) {
            throw ValidationException::withMessages([
                'criteria' => ['Provide exactly ' . self::CRITERIA_COUNT . ' learning areas.'],
            ]);
        }

        $criteria = [];
        foreach (array_values($source) as $index => $candidate) {
            $label = trim((string) (is_array($candidate) ? ($candidate['label'] ?? '') : $candidate));
            if ($label === '') {
                throw ValidationException::withMessages([
                    'criteria.' . $index . '.label' => ['Enter a name for learning area ' . ($index + 1) . '.'],
                ]);
            }

            $criteria[] = [
                'key' => 'learning_area_' . ($index + 1),
                'label' => mb_substr($label, 0, 80),
            ];
        }

        return ['criteria' => $criteria];
    }

    public static function observations(mixed $value, mixed $schema): array
    {
        $source = is_array($value) ? $value : [];
        $observations = [];

        foreach (self::normalize($schema)['criteria'] as $criterion) {
            $observations[$criterion['key']] = mb_substr(trim((string) ($source[$criterion['key']] ?? '')), 0, 1000);
        }

        return $observations;
    }
}