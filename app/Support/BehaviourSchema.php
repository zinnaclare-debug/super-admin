<?php

namespace App\Support;

use App\Models\StudentBehaviourRating;

final class BehaviourSchema
{
    public const CRITERIA_COUNT = 10;

    private const DEFAULT_LABELS = [
        'Handwriting', 'Speech', 'Attitude', 'Reading', 'Punctuality',
        'Teamwork', 'Self Control', 'Neatness', 'Honesty', 'Obedience',
    ];

    private const LEGACY_FIELDS = [
        'handwriting', 'speech', 'attitude', 'reading', 'punctuality', 'teamwork', 'self_control',
    ];

    public static function normalize(mixed $value): array
    {
        $source = is_array($value) ? ($value['criteria'] ?? $value) : [];
        $criteria = [];

        for ($index = 0; $index < self::CRITERIA_COUNT; $index++) {
            $candidate = is_array($source) ? ($source[$index] ?? []) : [];
            $label = trim((string) (is_array($candidate) ? ($candidate['label'] ?? '') : $candidate));
            $criteria[] = [
                'key' => 'criterion_' . ($index + 1),
                'label' => mb_substr($label !== '' ? $label : self::DEFAULT_LABELS[$index], 0, 60),
            ];
        }

        return ['criteria' => $criteria];
    }

    public static function values(?StudentBehaviourRating $rating, mixed $schema): array
    {
        $criteria = self::normalize($schema)['criteria'];
        $stored = is_array($rating?->ratings) ? $rating->ratings : [];
        $values = [];

        foreach ($criteria as $index => $criterion) {
            $legacyField = self::LEGACY_FIELDS[$index] ?? null;
            $raw = $stored[$criterion['key']] ?? ($legacyField ? ($rating?->{$legacyField} ?? 0) : 0);
            $values[$criterion['key']] = max(0, min(5, (int) $raw));
        }

        return $values;
    }

    public static function traits(?StudentBehaviourRating $rating, mixed $schema): array
    {
        $criteria = self::normalize($schema)['criteria'];
        $values = self::values($rating, $schema);

        return array_map(fn (array $criterion) => [
            'label' => $criterion['label'],
            'value' => $values[$criterion['key']] ?? 0,
        ], $criteria);
    }

    public static function legacyValues(array $values): array
    {
        $legacy = [];
        foreach (self::LEGACY_FIELDS as $index => $field) {
            $legacy[$field] = max(0, min(5, (int) ($values['criterion_' . ($index + 1)] ?? 0)));
        }

        return $legacy;
    }
}
