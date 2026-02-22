<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        static fn ($origin) => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
    ))),

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        static function ($pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                return null;
            }

            $delimiters = ['/', '#', '~', '%', '!'];
            if (in_array($pattern[0], $delimiters, true)) {
                return $pattern;
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
