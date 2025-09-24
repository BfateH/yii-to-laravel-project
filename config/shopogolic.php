<?php

return [
    'base_url' => env('SHOPOGOLIC_BASE_URL', 'https://shopogolic.net/api/'),
    'auth_key' => env('SHOPOGOLIC_AUTH_KEY', ''),
    'timeout' => env('SHOPOGOLIC_TIMEOUT', 30),
    'log_enabled' => env('SHOPOGOLIC_LOG_ENABLED', true),
    'log_channel' => env('SHOPOGOLIC_LOG_CHANNEL', 'stack'),
];
