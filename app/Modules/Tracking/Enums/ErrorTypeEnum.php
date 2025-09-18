<?php

namespace App\Modules\Tracking\Enums;

enum ErrorTypeEnum: string
{
    case NETWORK = 'network'; // Сетевые ошибки, таймауты
    case CLIENT = 'client';   // Ошибки клиента (4xx), например, неверный трек-номер
    case SERVER = 'server';   // Ошибки сервера (5xx)
    case RATE_LIMIT = 'rate_limit'; // Превышение лимита запросов (429)
    case UNKNOWN = 'unknown'; // Неизвестные ошибки
}
?>
