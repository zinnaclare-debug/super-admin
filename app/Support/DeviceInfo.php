<?php

namespace App\Support;

class DeviceInfo
{
    public static function fromUserAgent(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);
        $lower = strtolower($ua);

        return [
            'device_type' => self::deviceType($lower),
            'device_model' => self::deviceModel($ua),
            'browser' => self::browser($ua, $lower),
            'platform' => self::platform($ua, $lower),
            'raw_user_agent' => $ua,
        ];
    }

    private static function deviceType(string $lower): string
    {
        if (str_contains($lower, 'ipad') || str_contains($lower, 'tablet')) {
            return 'tablet';
        }

        if (
            str_contains($lower, 'mobile')
            || str_contains($lower, 'iphone')
            || str_contains($lower, 'android')
            || str_contains($lower, 'phone')
        ) {
            return 'phone';
        }

        return 'desktop';
    }

    private static function browser(string $ua, string $lower): string
    {
        if (preg_match('/Edg\/([0-9.]+)/', $ua, $m)) {
            return 'Edge ' . $m[1];
        }
        if (preg_match('/OPR\/([0-9.]+)/', $ua, $m)) {
            return 'Opera ' . $m[1];
        }
        if (preg_match('/Chrome\/([0-9.]+)/', $ua, $m) && !str_contains($lower, 'edg')) {
            return 'Chrome ' . $m[1];
        }
        if (preg_match('/Firefox\/([0-9.]+)/', $ua, $m)) {
            return 'Firefox ' . $m[1];
        }
        if (preg_match('/Version\/([0-9.]+).*Safari\//', $ua, $m)) {
            return 'Safari ' . $m[1];
        }

        return 'Unknown browser';
    }

    private static function platform(string $ua, string $lower): string
    {
        if (preg_match('/Android\s+([0-9.]+)/i', $ua, $m)) {
            return 'Android ' . $m[1];
        }
        if (preg_match('/CPU (?:iPhone )?OS\s+([0-9_]+)/i', $ua, $m)) {
            return 'iOS ' . str_replace('_', '.', $m[1]);
        }
        if (preg_match('/Windows NT\s+([0-9.]+)/i', $ua, $m)) {
            return 'Windows NT ' . $m[1];
        }
        if (str_contains($lower, 'mac os x')) {
            return 'macOS';
        }
        if (str_contains($lower, 'linux')) {
            return 'Linux';
        }

        return 'Unknown platform';
    }

    private static function deviceModel(string $ua): ?string
    {
        if (str_contains($ua, 'iPhone')) {
            return 'iPhone';
        }
        if (str_contains($ua, 'iPad')) {
            return 'iPad';
        }

        if (preg_match('/Android\s+[0-9.]+;\s*([^;)]+?)(?:\s+Build|\)|;)/i', $ua, $m)) {
            $model = trim($m[1]);
            return $model !== '' ? $model : 'Android device';
        }

        if (str_contains($ua, 'Windows')) {
            return 'Windows PC';
        }
        if (str_contains($ua, 'Macintosh')) {
            return 'Mac';
        }

        return null;
    }
}
