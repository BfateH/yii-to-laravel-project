<?php

return [
    'providers' => [
        'test' => [
            'jwks_url' => 'https://token.dev/jwks/keys.json',
            'allowed_issuers' => ['https://token.dev'],
            'allowed_audiences' => ['api://default'],
        ]

        // Другие провайдеры...
    ],
    'jwks_cache_ttl' => env('JWKS_CACHE_TTL', 3600)
];
