<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Support\Str;

class SchoolPublicWebsiteData
{
    public static function availableClasses(?School $school): array
    {
        return collect(self::availableClassGroups($school))
            ->flatMap(fn (array $group) => $group['classes'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique(fn ($name) => strtolower($name))
            ->values()
            ->all();
    }

    public static function availableClassGroups(?School $school): array
    {
        if (! $school) {
            return [];
        }

        $normalized = ClassTemplateSchema::normalize($school->class_templates);

        return collect(ClassTemplateSchema::activeSections($normalized))
            ->map(function (array $section) {
                $classes = collect(ClassTemplateSchema::activeClassNames($section))
                    ->map(fn ($name) => trim((string) $name))
                    ->filter(fn ($name) => $name !== '')
                    ->unique(fn ($name) => strtolower($name))
                    ->values()
                    ->all();

                return [
                    'key' => (string) ($section['key'] ?? ''),
                    'label' => trim((string) ($section['label'] ?? '')) ?: 'Level',
                    'classes' => $classes,
                ];
            })
            ->filter(fn (array $group) => ! empty($group['classes']))
            ->values()
            ->all();
    }

    public static function normalizeWebsiteContent(?array $value, ?School $school = null): array
    {
        $value = is_array($value) ? $value : [];
        $schoolName = trim((string) ($school?->name ?? ''));
        $contactEmail = trim((string) ($school?->contact_email ?? $school?->email ?? ''));
        $contactPhone = trim((string) ($school?->contact_phone ?? ''));
        $location = trim((string) ($school?->location ?? ''));

        return [
            'hero_title' => self::string($value['hero_title'] ?? null, $schoolName !== '' ? "Welcome to {$schoolName}" : 'Welcome to Our School', 160),
            'hero_subtitle' => self::string($value['hero_subtitle'] ?? null, '', 600),
            'motto' => self::string($value['motto'] ?? null, '', 255),
            'about_title' => self::string($value['about_title'] ?? null, 'About Our School', 120),
            'about_text' => self::string($value['about_text'] ?? null, $schoolName !== '' ? "Learn more about {$schoolName}, our values, and the learning experience we provide for every child." : 'Learn more about our values and learning experience.', 3000),
            'core_values_text' => self::string($value['core_values_text'] ?? null, '', 3000),
            'vision_text' => self::string($value['vision_text'] ?? null, '', 3000),
            'mission_text' => self::string($value['mission_text'] ?? null, '', 3000),
            'admissions_intro' => self::string($value['admissions_intro'] ?? null, 'Complete the application form to begin admission processing for your child.', 1200),
            'address' => self::string($value['address'] ?? null, $location, 255),
            'contact_email' => self::string($value['contact_email'] ?? null, $contactEmail, 255),
            'contact_phone' => self::string($value['contact_phone'] ?? null, $contactPhone, 40),
            'primary_color' => self::color($value['primary_color'] ?? null, '#0f172a'),
            'accent_color' => self::color($value['accent_color'] ?? null, '#0f766e'),
            'show_apply_now' => self::bool($value['show_apply_now'] ?? true, true),
            'show_entrance_exam' => self::bool($value['show_entrance_exam'] ?? true, true),
            'show_verify_score' => self::bool($value['show_verify_score'] ?? true, true),
        ];
    }

    public static function normalizeEntranceExamConfig(?array $value, array $availableClasses = []): array
    {
        $value = is_array($value) ? $value : [];
        $classExamMap = [];

        foreach ((array) ($value['class_exams'] ?? []) as $exam) {
            $normalizedExam = self::normalizeClassExam($exam);
            if ($normalizedExam['class_name'] === '') {
                continue;
            }

            $classExamMap[strtolower($normalizedExam['class_name'])] = $normalizedExam;
        }

        $normalizedClassExams = [];
        foreach ($availableClasses as $className) {
            $key = strtolower(trim((string) $className));
            $normalizedClassExams[] = $classExamMap[$key] ?? self::defaultClassExam($className);
            unset($classExamMap[$key]);
        }

        foreach ($classExamMap as $extraExam) {
            $normalizedClassExams[] = $extraExam;
        }

        $applicationFeeAmount = self::money($value['application_fee_amount'] ?? 0, 0);
        $applicationFeeTaxRate = self::money($value['application_fee_tax_rate'] ?? 1.6, 1.6, 0, 100);
        $applicationFeeTaxAmount = self::money($applicationFeeAmount * ($applicationFeeTaxRate / 100), 0);
        $applicationFeeTotal = self::money($applicationFeeAmount + $applicationFeeTaxAmount, 0);

        $normalizedClassExams = array_values(array_map(
            static fn (array $exam): array => self::applyClassExamFeeDefaults($exam, $applicationFeeAmount, $applicationFeeTaxRate),
            $normalizedClassExams
        ));

        return [
            'enabled' => self::bool($value['enabled'] ?? false, false),
            'application_open' => self::bool($value['application_open'] ?? true, true),
            'verification_open' => self::bool($value['verification_open'] ?? true, true),
            'application_fee_amount' => $applicationFeeAmount,
            'application_fee_tax_rate' => $applicationFeeTaxRate,
            'application_fee_tax_amount' => $applicationFeeTaxAmount,
            'application_fee_total' => $applicationFeeTotal,
            'apply_intro' => self::string($value['apply_intro'] ?? null, 'Fill the form below and keep your application number for the next admission steps.', 1500),
            'exam_intro' => self::string($value['exam_intro'] ?? null, 'Enter your application number to take the entrance examination assigned to your selected class.', 1500),
            'verify_intro' => self::string($value['verify_intro'] ?? null, 'Use your application number to verify your entrance exam result.', 1500),
            'class_exams' => $normalizedClassExams,
        ];
    }

    public static function findClassExam(array $config, string $className): ?array
    {
        $normalizedName = strtolower(trim($className));
        if ($normalizedName === '') {
            return null;
        }

        foreach ((array) ($config['class_exams'] ?? []) as $exam) {
            if (strtolower(trim((string) ($exam['class_name'] ?? ''))) === $normalizedName) {
                return self::normalizeClassExam($exam);
            }
        }

        return null;
    }

    public static function publicFacingClassExam(?array $classExam): ?array
    {
        if (! $classExam) {
            return null;
        }

        return [
            'class_name' => $classExam['class_name'],
            'enabled' => (bool) $classExam['enabled'],
            'duration_minutes' => (int) $classExam['duration_minutes'],
            'pass_mark' => (int) $classExam['pass_mark'],
            'instructions' => (string) $classExam['instructions'],
            'application_fee_amount' => (float) ($classExam['application_fee_amount'] ?? 0),
            'application_fee_tax_rate' => (float) ($classExam['application_fee_tax_rate'] ?? 1.6),
            'application_fee_tax_amount' => (float) ($classExam['application_fee_tax_amount'] ?? 0),
            'application_fee_total' => (float) ($classExam['application_fee_total'] ?? 0),
            'question_count' => count((array) ($classExam['questions'] ?? [])),
            'questions' => array_values(array_map(
                static fn (array $question, int $index) => [
                    'id' => $index + 1,
                    'question' => $question['question'],
                    'option_a' => $question['option_a'],
                    'option_b' => $question['option_b'],
                    'option_c' => $question['option_c'],
                    'option_d' => $question['option_d'],
                ],
                (array) ($classExam['questions'] ?? []),
                array_keys((array) ($classExam['questions'] ?? []))
            )),
        ];
    }

    private static function normalizeClassExam(mixed $exam): array
    {
        $exam = is_array($exam) ? $exam : [];
        $className = self::string($exam['class_name'] ?? null, '', 80);

        return [
            'class_name' => $className,
            'enabled' => self::bool($exam['enabled'] ?? false, false),
            'duration_minutes' => self::integer($exam['duration_minutes'] ?? 30, 5, 180, 30),
            'pass_mark' => self::integer($exam['pass_mark'] ?? 50, 0, 100, 50),
            'instructions' => self::string($exam['instructions'] ?? null, '', 3000),
            'application_fee_amount' => self::money($exam['application_fee_amount'] ?? 0, 0),
            'application_fee_tax_rate' => self::money($exam['application_fee_tax_rate'] ?? 1.6, 1.6, 0, 100),
            'application_fee_tax_amount' => self::money(($exam['application_fee_amount'] ?? 0) * (($exam['application_fee_tax_rate'] ?? 1.6) / 100), 0),
            'application_fee_total' => self::money(($exam['application_fee_amount'] ?? 0) + (($exam['application_fee_amount'] ?? 0) * (($exam['application_fee_tax_rate'] ?? 1.6) / 100)), 0),
            'questions' => self::normalizeQuestions($exam['questions'] ?? []),
        ];
    }

    private static function defaultClassExam(string $className): array
    {
        return [
            'class_name' => trim($className),
            'enabled' => false,
            'duration_minutes' => 30,
            'pass_mark' => 50,
            'instructions' => '',
            'application_fee_amount' => 0,
            'application_fee_tax_rate' => 1.6,
            'application_fee_tax_amount' => 0,
            'application_fee_total' => 0,
            'questions' => [],
        ];
    }

    private static function applyClassExamFeeDefaults(array $exam, float|int $defaultAmount, float|int $defaultTaxRate): array
    {
        $amount = array_key_exists('application_fee_amount', $exam)
            ? self::money($exam['application_fee_amount'] ?? 0, (float) $defaultAmount, 0, 10000000)
            : self::money($defaultAmount, 0);

        $taxRate = array_key_exists('application_fee_tax_rate', $exam)
            ? self::money($exam['application_fee_tax_rate'] ?? $defaultTaxRate, (float) $defaultTaxRate, 0, 100)
            : self::money($defaultTaxRate, 1.6, 0, 100);

        $taxAmount = self::money($amount * ($taxRate / 100), 0);
        $total = self::money($amount + $taxAmount, 0);

        $exam['application_fee_amount'] = $amount;
        $exam['application_fee_tax_rate'] = $taxRate;
        $exam['application_fee_tax_amount'] = $taxAmount;
        $exam['application_fee_total'] = $total;

        return $exam;
    }

    private static function normalizeQuestions(mixed $questions): array
    {
        $questions = is_array($questions) ? $questions : [];
        $normalized = [];

        foreach ($questions as $question) {
            $question = is_array($question) ? $question : [];
            $normalizedQuestion = [
                'id' => self::string($question['id'] ?? null, (string) Str::uuid(), 80),
                'subject_id' => self::nullableInteger($question['subject_id'] ?? null),
                'subject_name' => self::string($question['subject_name'] ?? null, '', 120),
                'question_bank_question_id' => self::nullableInteger($question['question_bank_question_id'] ?? null),
                'source_type' => self::sourceType($question['source_type'] ?? null),
                'question' => self::string($question['question'] ?? null, '', 500),
                'option_a' => self::string($question['option_a'] ?? null, '', 255),
                'option_b' => self::string($question['option_b'] ?? null, '', 255),
                'option_c' => self::string($question['option_c'] ?? null, '', 255),
                'option_d' => self::string($question['option_d'] ?? null, '', 255),
                'correct_option' => self::correctOption($question['correct_option'] ?? null),
            ];

            if (
                $normalizedQuestion['question'] === ''
                && $normalizedQuestion['option_a'] === ''
                && $normalizedQuestion['option_b'] === ''
                && $normalizedQuestion['option_c'] === ''
                && $normalizedQuestion['option_d'] === ''
            ) {
                continue;
            }

            if (
                $normalizedQuestion['question'] === ''
                || $normalizedQuestion['option_a'] === ''
                || $normalizedQuestion['option_b'] === ''
                || $normalizedQuestion['option_c'] === ''
                || $normalizedQuestion['option_d'] === ''
                || $normalizedQuestion['correct_option'] === ''
            ) {
                continue;
            }

            $normalized[] = $normalizedQuestion;
        }

        return array_values($normalized);
    }

    private static function string(mixed $value, string $fallback = '', int $max = 255): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            $text = $fallback;
        }

        return mb_substr($text, 0, $max);
    }

    private static function color(mixed $value, string $fallback): string
    {
        $color = trim((string) ($value ?? ''));
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : $fallback;
    }

    private static function bool(mixed $value, bool $fallback): bool
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value;
        }

        $validated = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return $validated ?? (bool) $value;
    }

    private static function integer(mixed $value, int $min, int $max, int $fallback): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($number === null) {
            return $fallback;
        }

        return max($min, min($max, $number));
    }


    private static function money(mixed $value, float $fallback = 0.0, float $min = 0.0, float $max = 100000000.0): float
    {
        $number = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        if ($number === null) {
            $number = $fallback;
        }

        $bounded = max($min, min($max, (float) $number));
        return round($bounded, 2);
    }
    private static function nullableInteger(mixed $value): ?int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        return $number === null ? null : (int) $number;
    }

    private static function sourceType(mixed $value): string
    {
        $sourceType = strtolower(trim((string) ($value ?? '')));
        return in_array($sourceType, ['manual', 'question_bank', 'ai'], true) ? $sourceType : 'manual';
    }

    private static function correctOption(mixed $value): string
    {
        $option = strtoupper(trim((string) ($value ?? '')));
        return in_array($option, ['A', 'B', 'C', 'D'], true) ? $option : '';
    }
}







