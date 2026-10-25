<?php

$roles = static function (string $key, string $default): array {
    return array_values(array_unique(array_filter(array_map(
        static fn (string $role): int => (int) trim($role),
        explode(',', (string) env($key, $default))
    ))));
};

return [
    'auto_publish_mobile' => (bool) env('AUTO_PUBLISH_MOBILE_SIGNAL', false),
    'realtime_enabled' => (bool) env('SIGNAL_REALTIME_ENABLED', true),
    'discord_enabled' => (bool) env('SIGNAL_DISCORD_ENABLED', true),
    'discord_webhook_url' => env('DISCORD_SIGNAL_WEBHOOK_URL'),
    'discord_timeout_seconds' => (int) env('DISCORD_SIGNAL_TIMEOUT_SECONDS', 10),
    'display_timezone' => env('SIGNAL_DISPLAY_TIMEZONE', 'Asia/Kuala_Lumpur'),
    'firebase_web_api_key' => env('FIREBASE_WEB_API_KEY'),
    'firebase_auth_timeout_seconds' => (int) env('FIREBASE_AUTH_TIMEOUT_SECONDS', 5),
    'firebase_project_id' => env('FIREBASE_PROJECT_ID'),
    'firebase_client_email' => env('FIREBASE_CLIENT_EMAIL'),
    'firebase_private_key' => env('FIREBASE_PRIVATE_KEY'),
    'firebase_signal_topic' => env('FIREBASE_SIGNAL_TOPIC', 'hc_trading_signals'),
    'roles' => [
        'create' => $roles('SIGNAL_CREATE_ROLE_IDS', '1,2,3,201,202,502,999'),
        'publish' => $roles('SIGNAL_PUBLISH_ROLE_IDS', '1,2,3,201,202,502,999'),
        'manage' => $roles('SIGNAL_MANAGE_ROLE_IDS', '1,2,3,502,999'),
    ],
];
