<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class CommentOnlySchema
{
    public const DEFAULT_CRITERIA_COUNT = 10;
    public const MAX_CRITERIA_COUNT = 15;

    public static function defaults(): array
    {
        return [
            'criteria' => self::defaultCriteria(self::DEFAULT_CRITERIA_COUNT),
        ];
    }

    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        $source = is_array($value) ? ($value['criteria'] ?? $value) : [];
        if (!is_array($source) || empty($source)) {
            return self::defaults();
        }

        $criteria = [];
        foreach (array_slice(array_values($source), 0, self::MAX_CRITERIA_COUNT) as $index => $candidate) {
            $label = trim((string) (is_array($candidate) ? ($candidate['label'] ?? '') : $candidate));
            $criteria[] = [
                'key' => 'learning_area_' . ($index + 1),
                'label' => mb_substr($label !== '' ? $label : ('Learning Area ' . ($index + 1)), 0, 80),
            ];
        }

        return ['criteria' => $criteria ?: self::defaultCriteria(self::DEFAULT_CRITERIA_COUNT)];
    }

    public static function validateAndNormalize(mixed $value): array
    {
        $source = is_array($value) ? ($value['criteria'] ?? $value) : null;
        if (!is_array($source) || count($source) < 1 || count($source) > self::MAX_CRITERIA_COUNT) {
            throw ValidationException::withMessages([
                'criteria' => ['Provide between 1 and ' . self::MAX_CRITERIA_COUNT . ' learning areas.'],
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
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        $source = is_array($value) ? $value : [];
        $observations = [];

        foreach (self::normalize($schema)['criteria'] as $criterion) {
            $observations[$criterion['key']] = mb_substr(trim((string) ($source[$criterion['key']] ?? '')), 0, 1000);
        }

        return $observations;
    }

    private static function defaultCriteria(int $count): array
    {
        return array_map(
            fn (int $index) => [
                'key' => 'learning_area_' . $index,
                'label' => 'Learning Area ' . $index,
            ],
            range(1, $count)
        );
    }
}