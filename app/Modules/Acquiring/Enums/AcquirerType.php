<?php

namespace App\Modules\Acquiring\Enums;

enum AcquirerType: string
{
    case TINKOFF = 'tinkoff';
    // Добавить другие эквайринг-провайдеры позже
    // case SBERBANK = 'sberbank';
}
