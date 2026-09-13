<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        static fn ($origin) => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,https://localhost'))
    ))),

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        static function ($pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                return null;
            }

            $delimiters = ['/', '#', '~', '%', '!'];
            $firstCharacter = $pattern[0];
            if (in_array($firstCharacter, $delimiters, true)) {
                // Only retain a supplied delimiter when its matching closing delimiter exists.
                if (str_ends_with($pattern, $firstCharacter)) {
                    return $pattern;
                }
                $pattern = substr($pattern, 1);
            }

            return '#' . $pattern . '#';
        },
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
