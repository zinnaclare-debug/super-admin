<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PlatformHomeContent
{
    private const STORAGE_PATH = 'platform-home-content.json';

    public static function defaults(): array
    {
        return [
            'about_text' => 'LyteBridge Professional Services is a dynamic and innovative solutions provider specializing in Education, ICT, and School Management Software. We are committed to helping schools, educational institutions, and organizations improve efficiency, embrace digital transformation, and operate with professional standards.',
            'vision_text' => 'Our services are designed to simplify school administration, enhance teaching and learning, and provide reliable technology solutions tailored to modern educational needs. From school setup and educational consulting to ICT infrastructure and complete school management systems, LyteBridge delivers affordable, user-friendly, and scalable solutions.',
            'mission_text' => 'At LyteBridge Professional Services, we focus on professionalism, innovation, and excellence. Our goal is to empower institutions to go paperless, save cost, improve productivity, and manage their operations smarter through technology-driven solutions.',
            'content_section_title' => 'Content Area',
            'content_section_intro' => 'Use this space to present the platform rollout plan, onboarding highlights, and the next steps schools should follow before launch.',
            'content_todo_items' => [
                'Plan school onboarding, admin access, and rollout timeline.',
                'Prepare portal content, branding, and staff orientation materials.',
                'Launch admissions, payments, results, and communication workflows.',
            ],
        ];
    }

    public static function load(): array
    {
        if (! Storage::disk('local')->exists(self::STORAGE_PATH)) {
            return self::defaults();
        }

        $decoded = json_decode((string) Storage::disk('local')->get(self::STORAGE_PATH), true);

        return self::normalize(is_array($decoded) ? $decoded : []);
    }

    public static function save(?array $value): array
    {
        $normalized = self::normalize($value ?? []);
        Storage::disk('local')->put(self::STORAGE_PATH, json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $normalized;
    }

    public static function normalize(?array $value): array
    {
        $value = is_array($value) ? $value : [];
        $defaults = self::defaults();

        $items = $value['content_todo_items'] ?? $defaults['content_todo_items'];
        if (is_string($items)) {
            $items = preg_split('/\r\n|\r|\n/', $items) ?: [];
        }

        $normalizedItems = collect(is_array($items) ? $items : [])
            ->map(fn ($item) => self::string($item, '', 220))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->take(8)
            ->all();

        if ($normalizedItems === []) {
            $normalizedItems = $defaults['content_todo_items'];
        }

        return [
            'about_text' => self::string($value['about_text'] ?? null, $defaults['about_text'], 3000),
            'vision_text' => self::string($value['vision_text'] ?? null, $defaults['vision_text'], 3000),
            'mission_text' => self::string($value['mission_text'] ?? null, $defaults['mission_text'], 3000),
            'content_section_title' => self::string($value['content_section_title'] ?? null, $defaults['content_section_title'], 120),
            'content_section_intro' => self::string($value['content_section_intro'] ?? null, $defaults['content_section_intro'], 1200),
            'content_todo_items' => $normalizedItems,
        ];
    }

    private static function string(mixed $value, string $fallback = '', int $max = 255): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            $text = $fallback;
        }

        return mb_substr($text, 0, $max);
    }
}
