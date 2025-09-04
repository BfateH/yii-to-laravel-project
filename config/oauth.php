<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'guzzle' => [
            'verify' => config('app.env') !== 'local',
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => config('app.env') !== 'local',
                CURLOPT_SSL_VERIFYHOST => config('app.env') !== 'local',
            ]
        ]
    ],

    'yandex' => [
        'client_id' => env('YANDEX_CLIENT_ID'),
        'client_secret' => env('YANDEX_CLIENT_SECRET'),
        'redirect' => env('YANDEX_REDIRECT_URI'),
        'guzzle' => [
            'verify' => config('app.env') !== 'local',
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => config('app.env') !== 'local',
                CURLOPT_SSL_VERIFYHOST => config('app.env') !== 'local',
            ]
        ]
    ],

    'vkontakte' => [
        'client_id' => env('VKONTAKTE_CLIENT_ID'),
        'client_secret' => env('VKONTAKTE_CLIENT_SECRET'),
        'redirect' => env('VKONTAKTE_REDIRECT_URI'),
        'guzzle' => [
            'verify' => config('app.env') !== 'local',
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => config('app.env') !== 'local',
                CURLOPT_SSL_VERIFYHOST => config('app.env') !== 'local',
            ]
        ]
    ],

    'mailru' => [
        'client_id' => env('MAILRU_CLIENT_ID'),
        'client_secret' => env('MAILRU_CLIENT_SECRET'),
        'redirect' => env('MAILRU_REDIRECT_URI'),
        'guzzle' => [
            'verify' => config('app.env') !== 'local',
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => config('app.env') !== 'local',
                CURLOPT_SSL_VERIFYHOST => config('app.env') !== 'local',
            ]
        ]
    ],
];
