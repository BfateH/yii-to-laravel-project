<?php

return [
    'tinkoff' => [
        'name' => 'tinkoff',
        'display_name' => 'Т-Банк',
        'service_class' => \App\Modules\Acquiring\Services\TinkoffAcquirer::class,
        'required_fields' => [
            'terminal_key',
            'password',
            'secret_key',
        ],
        'secret_fields' => ['terminal_key', 'password', 'secret_key'],
        'description' => 'Т-Банк Acquiring integration'
    ],
    // Добавлять новые эквайринг-провайдеры аналогично:
//    'sberbank' => [
//        'name' => 'sberbank',
//        'display_name' => 'Сбербанк',
//        'service_class' => '',
//        'required_fields' => [
//            'merchant_login',
//            'merchant_password',
//            'api_key',
//        ],
//        'secret_fields' => ['merchant_login', 'merchant_password', 'api_key'],
//        'description' => 'Сбербанк Acquiring integration'
//    ],

];
