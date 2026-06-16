<?php

namespace App\Support;

class ResultPdfTemplate
{
    public static function default(): array
    {
        return [
            'layout' => 'classic',
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
            'third_term' => [
                'show_previous_term_totals' => true,
            ],
            'cumulative' => [
                'show_term_totals' => true,
                'show_average' => true,
            ],
        ];
    }

    public static function normalize(mixed $raw): array
    {
        $source = is_array($raw) ? $raw : [];
        $base = self::default();

        $layout = strtolower(trim((string) ($source['layout'] ?? $base['layout'])));
        if (!in_array($layout, ['classic', 'compact'], true)) {
            $layout = $base['layout'];
        }

        $watermarkOpacity = (float) ($source['watermark_opacity'] ?? $base['watermark_opacity']);
        $watermarkOpacity = max(0, min(0.2, $watermarkOpacity));

        $thirdTerm = is_array($source['third_term'] ?? null) ? $source['third_term'] : [];
        $cumulative = is_array($source['cumulative'] ?? null) ? $source['cumulative'] : [];

        return [
            'layout' => $layout,
            'primary_color' => self::color($source['primary_color'] ?? null, $base['primary_color']),
            'accent_color' => self::color($source['accent_color'] ?? null, $base['accent_color']),
            'watermark_opacity' => $watermarkOpacity,
            'show_student_photo' => self::bool($source, 'show_student_photo', $base['show_student_photo']),
            'show_school_logo' => self::bool($source, 'show_school_logo', $base['show_school_logo']),
            'show_watermark' => self::bool($source, 'show_watermark', $base['show_watermark']),
            'show_attendance' => self::bool($source, 'show_attendance', $base['show_attendance']),
            'show_behaviour' => self::bool($source, 'show_behaviour', $base['show_behaviour']),
            'show_signature' => self::bool($source, 'show_signature', $base['show_signature']),
            'show_result_position' => self::bool($source, 'show_result_position', $base['show_result_position']),
            'third_term' => [
                'show_previous_term_totals' => self::bool(
                    $thirdTerm,
                    'show_previous_term_totals',
                    $base['third_term']['show_previous_term_totals']
                ),
            ],
            'cumulative' => [
                'show_term_totals' => self::bool($cumulative, 'show_term_totals', $base['cumulative']['show_term_totals']),
                'show_average' => self::bool($cumulative, 'show_average', $base['cumulative']['show_average']),
            ],
        ];
    }

    public static function forPdf(mixed $raw, string $resultType, ?string $termName): array
    {
        $template = self::normalize($raw);
        $isThirdTerm = self::termOrderFromName($termName) === 3;

        $template['is_third_term'] = $isThirdTerm;
        $template['show_third_term_previous_totals'] =
            $resultType !== 'cumulative'
            && $isThirdTerm
            && (bool) ($template['third_term']['show_previous_term_totals'] ?? true);

        return $template;
    }

    public static function termOrderFromName(?string $name): ?int
    {
        $value = strtolower(trim((string) $name));
        if ($value === '') {
            return null;
        }

        return match (true) {
            str_contains($value, 'first') || preg_match('/(^|\D)1(st)?(\D|$)/', $value) === 1 => 1,
            str_contains($value, 'second') || preg_match('/(^|\D)2(nd)?(\D|$)/', $value) === 1 => 2,
            str_contains($value, 'third') || str_contains($value, 'three') || preg_match('/(^|\D)3(rd)?(\D|$)/', $value) === 1 => 3,
            default => null,
        };
    }

    private static function bool(array $source, string $key, bool $fallback): bool
    {
        if (!array_key_exists($key, $source)) {
            return $fallback;
        }

        return filter_var($source[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    private static function color(mixed $value, string $fallback): string
    {
        $color = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtoupper($color) : $fallback;
    }
}
