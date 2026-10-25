<?php

$roles = static function (string $key, string $default): array {
    return array_values(array_unique(array_filter(array_map(
        static fn (string $role): int => (int) trim($role),
        explode(',', (string) env($key, $default))
    ))));
};

return [
    'realtime_enabled' => (bool) env('TRADING_BLOG_REALTIME_ENABLED', true),
    'publication_timezone' => env('TRADING_BLOG_PUBLICATION_TIMEZONE', 'Asia/Singapore'),
    'roles' => [
        'create' => $roles('TRADING_BLOG_CREATE_ROLE_IDS', '1,2,3'),
        'publish' => $roles('TRADING_BLOG_PUBLISH_ROLE_IDS', '1,2,3'),
        'manage' => $roles('TRADING_BLOG_MANAGE_ROLE_IDS', '1,2,3'),
    ],
];
