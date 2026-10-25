<?php

return [
    'provider' => env('MARKET_DATA_PROVIDER', 'twelvedata'),
    'mode' => strtoupper(env('MARKET_DATA_MODE', 'MOCK')),
    'symbol' => env('MARKET_DATA_SYMBOL', 'XAU/USD'),
    'api_key' => env('TWELVE_DATA_API_KEY'),
    'auto_refresh' => (bool) env('MARKET_DATA_AUTO_REFRESH', false),
    'history_enabled' => (bool) env('MARKET_DATA_HISTORY_ENABLED', true),
    'quote_refresh_seconds' => max(300, (int) env('MARKET_DATA_QUOTE_REFRESH_SECONDS', 300)),
    'request_timeout_seconds' => max(2, (int) env('MARKET_DATA_REQUEST_TIMEOUT_SECONDS', 10)),
    'publish_latency_seconds' => max(0, (int) env('MARKET_DATA_PUBLISH_LATENCY_SECONDS', 90)),
    'quote_stale_seconds' => max(300, (int) env('MARKET_DATA_QUOTE_STALE_SECONDS', 900)),
    'analysis_stale_multiplier' => max(2, (int) env('MARKET_DATA_ANALYSIS_STALE_MULTIPLIER', 3)),
    'session_timezone' => env('MARKET_DATA_SESSION_TIMEZONE', 'Australia/Sydney'),
    'distribution_allowed' => (bool) env('MARKET_DATA_DISTRIBUTION_ALLOWED', false),
];
