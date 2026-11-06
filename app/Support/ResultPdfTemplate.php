<?php

namespace App\Support;

class ResultPdfTemplate
{
    public static function default(): array
    {
        return [
            'layout' => 'compact',
            'layout_version' => 2,
            'score_profile_version' => 0,
            'score_profile' => self::scoreProfileDefaults('compact'),
            'primary_color' => '#111827',
            'accent_color' => '#1d4ed8',
            'watermark_opacity' => 0.07,
            'show_student_photo' => true,
            'show_school_logo' => true,
            'show_watermark' => true,
            'show_attendance' => true,
            'show_behaviour' => true,
            'show_signature' => true,
            'show_result_position' => true,
            'third_term' => ['show_previous_term_totals' => true],
            'cumulative' => ['show_term_totals' => true, 'show_average' => true],
        ];
    }

    public static function normalize(mixed $raw): array
    {
        $source = is_array($raw) ? $raw : [];
        $base = self::default();
        $layout = strtolower(trim((string) ($source['layout'] ?? $base['layout'])));
        if (!in_array($layout, ['classic', 'compact', 'comments_only'], true)) {
            $layout = $base['layout'];
        }

        $thirdTerm = is_array($source['third_term'] ?? null) ? $source['third_term'] : [];
        $cumulative = is_array($source['cumulative'] ?? null) ? $source['cumulative'] : [];

        return [
            'layout' => $layout,
            'layout_version' => 2,
            'score_profile_version' => max(0, (int) ($source['score_profile_version'] ?? 0)) >= 1 ? 1 : 0,
            'score_profile' => self::normalizeScoreProfile($source['score_profile'] ?? null, $layout),
            'primary_color' => self::color($source['primary_color'] ?? null, $base['primary_color']),
            'accent_color' => self::color($source['accent_color'] ?? null, $base['accent_color']),
            'watermark_opacity' => max(0, min(0.2, (float) ($source['watermark_opacity'] ?? $base['watermark_opacity']))),
            'show_student_photo' => self::bool($source, 'show_student_photo', $base['show_student_photo']),
            'show_school_logo' => self::bool($source, 'show_school_logo', $base['show_school_logo']),
            'show_watermark' => self::bool($source, 'show_watermark', $base['show_watermark']),
            'show_attendance' => self::bool($source, 'show_attendance', $base['show_attendance']),
            'show_behaviour' => self::bool($source, 'show_behaviour', $base['show_behaviour']),
            'show_signature' => self::bool($source, 'show_signature', $base['show_signature']),
            'show_result_position' => self::bool($source, 'show_result_position', $base['show_result_position']),
            'third_term' => ['show_previous_term_totals' => self::bool($thirdTerm, 'show_previous_term_totals', true)],
            'cumulative' => [
                'show_term_totals' => self::bool($cumulative, 'show_term_totals', true),
                'show_average' => self::bool($cumulative, 'show_average', true),
            ],
        ];
    }

    public static function scoreProfileDefaults(string $layout): array
    {
        $classic = $layout === 'classic';

        return [
            'ca_maxes' => $classic ? [4, 4, 4, 4, 4] : [6, 6, 6, 6, 6],
            'ca_labels' => ['CA1', 'CA2', 'CA3', 'CA4', 'CA5'],
            'psychomotor_maxes' => $classic ? [2, 2, 2, 2, 2] : [0, 0, 0, 0, 0],
            'psychomotor_labels' => ['P1', 'P2', 'P3', 'P4', 'P5'],
            'quiz_max' => $classic ? 5 : 0,
            'quiz_label' => 'Quiz',
            'note_copying_max' => $classic ? 5 : 0,
            'note_copying_label' => 'Note Copying',
            'exam_max' => $classic ? 60 : 70,
        ];
    }

    public static function normalizeScoreProfile(mixed $raw, string $layout): array
    {
        $defaults = self::scoreProfileDefaults($layout);
        $source = is_array($raw) ? $raw : [];
        $isClassic = $layout === 'classic';
        $maxes = isset($source['ca_maxes']) && is_array($source['ca_maxes']) ? $source['ca_maxes'] : $defaults['ca_maxes'];
        $labels = isset($source['ca_labels']) && is_array($source['ca_labels']) ? $source['ca_labels'] : $defaults['ca_labels'];
        $psychomotor = isset($source['psychomotor_maxes']) && is_array($source['psychomotor_maxes']) ? $source['psychomotor_maxes'] : $defaults['psychomotor_maxes'];
        $psychomotorLabels = isset($source['psychomotor_labels']) && is_array($source['psychomotor_labels']) ? $source['psychomotor_labels'] : $defaults['psychomotor_labels'];
        $normalizedMaxes = [];
        $normalizedLabels = [];
        $normalizedPsychomotor = [];
        $normalizedPsychomotorLabels = [];

        for ($index = 0; $index < 5; $index++) {
            $normalizedMaxes[$index] = max(0, min(100, (int) ($maxes[$index] ?? 0)));
            $label = trim((string) ($labels[$index] ?? ''));
            $normalizedLabels[$index] = $label !== '' ? substr($label, 0, 30) : 'CA' . ($index + 1);
            $normalizedPsychomotor[$index] = $isClassic ? max(0, min(100, (int) ($psychomotor[$index] ?? 0))) : 0;
            $psychomotorLabel = trim((string) ($psychomotorLabels[$index] ?? ''));
            $normalizedPsychomotorLabels[$index] = $psychomotorLabel !== '' ? substr($psychomotorLabel, 0, 30) : 'P' . ($index + 1);
        }

        $quiz = trim((string) ($source['quiz_label'] ?? $defaults['quiz_label']));
        $note = trim((string) ($source['note_copying_label'] ?? $defaults['note_copying_label']));

        return [
            'ca_maxes' => $normalizedMaxes,
            'ca_labels' => $normalizedLabels,
            'psychomotor_maxes' => $normalizedPsychomotor,
            'psychomotor_labels' => $normalizedPsychomotorLabels,
            'quiz_max' => $isClassic ? max(0, min(100, (int) ($source['quiz_max'] ?? $defaults['quiz_max']))) : 0,
            'quiz_label' => $quiz !== '' ? substr($quiz, 0, 30) : 'Quiz',
            'note_copying_max' => $isClassic ? max(0, min(100, (int) ($source['note_copying_max'] ?? $defaults['note_copying_max']))) : 0,
            'note_copying_label' => $note !== '' ? substr($note, 0, 30) : 'Note Copying',
            'exam_max' => max(0, min(100, (int) ($source['exam_max'] ?? $defaults['exam_max']))),
        ];
    }

    public static function validateScoreProfile(mixed $raw, string $layout): ?string
    {
        if (!is_array($raw) || !isset($raw['ca_maxes']) || !is_array($raw['ca_maxes'])) return 'Provide the five CA score allocations.';
        $maxes = array_values($raw['ca_maxes']);
        if (count($maxes) !== 5) return 'Exactly five CA score allocations are required.';
        $total = 0;
        foreach ($maxes as $max) {
            if (!is_numeric($max) || (int) $max < 0 || (int) $max > 100) return 'Each CA allocation must be a whole number between 0 and 100.';
            $total += (int) $max;
        }
        $exam = $raw['exam_max'] ?? null;
        if (!is_numeric($exam) || (int) $exam < 0 || (int) $exam > 100) return 'The examination allocation must be a whole number between 0 and 100.';
        $total += (int) $exam;

        if ($layout === 'classic') {
            $psychomotor = $raw['psychomotor_maxes'] ?? null;
            if (!is_array($psychomotor) || count($psychomotor) !== 5) return 'Provide the five Psychomotor / Affective score allocations.';
            foreach ($psychomotor as $max) {
                if (!is_numeric($max) || (int) $max < 0 || (int) $max > 100) return 'Each Psychomotor / Affective allocation must be between 0 and 100.';
                $total += (int) $max;
            }
            foreach (['quiz_max' => 'Quiz', 'note_copying_max' => 'Note Copying'] as $field => $label) {
                $max = $raw[$field] ?? null;
                if (!is_numeric($max) || (int) $max < 0 || (int) $max > 100) return $label . ' allocation must be between 0 and 100.';
                $total += (int) $max;
            }
        }

        return $total === 100 ? null : 'All assessment allocations combined must equal exactly 100.';
    }

    public static function forPdf(mixed $raw, string $resultType, ?string $termName): array
    {
        $template = self::normalize($raw);
        $isThirdTerm = self::termOrderFromName($termName) === 3;
        $template['is_third_term'] = $isThirdTerm;
        $template['show_third_term_previous_totals'] = $resultType !== 'cumulative' && $isThirdTerm && (bool) ($template['third_term']['show_previous_term_totals'] ?? true);
        return $template;
    }

    public static function termOrderFromName(?string $name): ?int
    {
        $value = strtolower(trim((string) $name));
        if ($value === '') return null;
        return match (true) {
            str_contains($value, 'first') || preg_match('/(^|\D)1(st)?(\D|$)/', $value) === 1 => 1,
            str_contains($value, 'second') || preg_match('/(^|\D)2(nd)?(\D|$)/', $value) === 1 => 2,
            str_contains($value, 'third') || str_contains($value, 'three') || preg_match('/(^|\D)3(rd)?(\D|$)/', $value) === 1 => 3,
            default => null,
        };
    }

    private static function bool(array $source, string $key, bool $fallback): bool
    {
        if (!array_key_exists($key, $source)) return $fallback;
        return filter_var($source[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    private static function color(mixed $value, string $fallback): string
    {
        $color = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtoupper($color) : $fallback;
    }
}
