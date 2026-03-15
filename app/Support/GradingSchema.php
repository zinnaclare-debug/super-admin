<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class GradingSchema
{
    public const MAX_ROWS = 10;

    public static function defaults(): array
    {
        return [
            ['from' => 0, 'to' => 29, 'grade' => 'F', 'remark' => 'FAIL'],
            ['from' => 30, 'to' => 39, 'grade' => 'E', 'remark' => 'POOR'],
            ['from' => 40, 'to' => 49, 'grade' => 'D', 'remark' => 'FAIR'],
            ['from' => 50, 'to' => 59, 'grade' => 'C', 'remark' => 'GOOD'],
            ['from' => 60, 'to' => 69, 'grade' => 'B', 'remark' => 'VERY GOOD'],
            ['from' => 70, 'to' => 100, 'grade' => 'A', 'remark' => 'EXCELLENT'],
        ];
    }

    public static function normalize(mixed $raw): array
    {
        $rows = self::extractRows($raw);
        if (empty($rows)) {
            return self::defaults();
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $from = isset($row['from']) && $row['from'] !== '' ? (int) $row['from'] : null;
            $to = isset($row['to']) && $row['to'] !== '' ? (int) $row['to'] : null;
            $grade = trim((string) ($row['grade'] ?? ''));
            $remark = trim((string) ($row['remark'] ?? ''));

            if ($from === null && $to === null && $grade === '' && $remark === '') {
                continue;
            }

            if ($from === null || $to === null || $grade === '') {
                return self::defaults();
            }

            $normalized[] = [
                'from' => $from,
                'to' => $to,
                'grade' => $grade,
                'remark' => $remark,
            ];
        }

        if (!self::isContinuous($normalized)) {
            return self::defaults();
        }

        return array_values($normalized);
    }

    public static function validateAndNormalize(mixed $raw): array
    {
        $rows = self::extractRows($raw);
        if (!is_array($rows) || empty($rows)) {
            throw ValidationException::withMessages([
                'grading_schema' => ['Add at least one grading row.'],
            ]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'grading_schema' => ['A grading system can have at most ' . self::MAX_ROWS . ' rows.'],
            ]);
        }

        $normalized = [];
        $blankEncountered = false;
        $expectedFrom = 0;

        foreach (array_values($rows) as $index => $row) {
            $label = 'grading_schema.' . $index;
            $fromRaw = is_array($row) ? ($row['from'] ?? null) : null;
            $toRaw = is_array($row) ? ($row['to'] ?? null) : null;
            $gradeRaw = is_array($row) ? ($row['grade'] ?? null) : null;
            $remarkRaw = is_array($row) ? ($row['remark'] ?? null) : null;

            $grade = trim((string) ($gradeRaw ?? ''));
            $remark = trim((string) ($remarkRaw ?? ''));
            $isBlank = ($fromRaw === null || $fromRaw === '')
                && ($toRaw === null || $toRaw === '')
                && $grade === ''
                && $remark === '';

            if ($isBlank) {
                $blankEncountered = true;
                continue;
            }

            if ($blankEncountered) {
                throw ValidationException::withMessages([
                    $label => ['Remove empty rows between grading entries.'],
                ]);
            }

            if (!is_numeric($fromRaw) || !is_numeric($toRaw)) {
                throw ValidationException::withMessages([
                    $label => ['Each grading row must include numeric From and To values.'],
                ]);
            }

            if ($grade === '') {
                throw ValidationException::withMessages([
                    $label . '.grade' => ['Enter a grade label for this row.'],
                ]);
            }

            $from = (int) $fromRaw;
            $to = (int) $toRaw;

            if ($from < 0 || $from > 100 || $to < 0 || $to > 100) {
                throw ValidationException::withMessages([
                    $label => ['Score ranges must stay between 0 and 100.'],
                ]);
            }

            if ($index === 0 && $from !== 0) {
                throw ValidationException::withMessages([
                    $label . '.from' => ['The first grading row must start from 0.'],
                ]);
            }

            if ($from !== $expectedFrom) {
                throw ValidationException::withMessages([
                    $label . '.from' => ['This row must start from ' . $expectedFrom . '.'],
                ]);
            }

            if ($to < $from) {
                throw ValidationException::withMessages([
                    $label . '.to' => ['The To value must be greater than or equal to From.'],
                ]);
            }

            $normalized[] = [
                'from' => $from,
                'to' => $to,
                'grade' => $grade,
                'remark' => $remark,
            ];

            $expectedFrom = $to + 1;
            if ($expectedFrom > 101) {
                throw ValidationException::withMessages([
                    $label . '.to' => ['Grading rows cannot go beyond 100.'],
                ]);
            }
        }

        if (empty($normalized)) {
            throw ValidationException::withMessages([
                'grading_schema' => ['Add at least one grading row.'],
            ]);
        }

        $lastRow = $normalized[count($normalized) - 1];
        if ((int) $lastRow['to'] !== 100) {
            throw ValidationException::withMessages([
                'grading_schema' => ['The final grading row must end at 100.'],
            ]);
        }

        return $normalized;
    }

    public static function resolve(mixed $raw, int $score): array
    {
        $rows = self::normalize($raw);
        $score = max(0, min(100, $score));

        foreach ($rows as $row) {
            if ($score >= (int) $row['from'] && $score <= (int) $row['to']) {
                return $row;
            }
        }

        return $rows[count($rows) - 1] ?? ['from' => 0, 'to' => 100, 'grade' => '-', 'remark' => ''];
    }

    public static function gradeForTotal(mixed $raw, int $score): string
    {
        return (string) (self::resolve($raw, $score)['grade'] ?? '-');
    }

    public static function remarkForTotal(mixed $raw, int $score): string
    {
        $remark = trim((string) (self::resolve($raw, $score)['remark'] ?? ''));
        return $remark !== '' ? $remark : '-';
    }

    public static function displayKey(mixed $raw): string
    {
        $rows = self::normalize($raw);

        return implode(' | ', array_map(
            fn (array $row) => sprintf('%s [%d-%d]', (string) $row['grade'], (int) $row['from'], (int) $row['to']),
            $rows
        ));
    }

    private static function extractRows(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            }
        }

        if (is_object($raw)) {
            $raw = json_decode(json_encode($raw), true);
        }

        if (is_array($raw) && isset($raw['rows']) && is_array($raw['rows'])) {
            return array_values($raw['rows']);
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    private static function isContinuous(array $rows): bool
    {
        if (empty($rows) || count($rows) > self::MAX_ROWS) {
            return false;
        }

        $expectedFrom = 0;
        foreach ($rows as $row) {
            $from = (int) ($row['from'] ?? -1);
            $to = (int) ($row['to'] ?? -1);
            $grade = trim((string) ($row['grade'] ?? ''));

            if ($grade === '' || $from !== $expectedFrom || $to < $from || $to > 100) {
                return false;
            }

            $expectedFrom = $to + 1;
        }

        return ((int) ($rows[count($rows) - 1]['to'] ?? -1)) === 100;
    }
}
