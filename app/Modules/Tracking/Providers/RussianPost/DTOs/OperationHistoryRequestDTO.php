<?php

namespace App\Modules\Tracking\Providers\RussianPost\DTOs;

/**
 * DTO для данных запроса getOperationHistory API Почты России.
 */
class OperationHistoryRequestDTO
{
    public function __construct(
        public readonly string $barcode, // Трек-номер
        public readonly int $messageType = 0, // 0 - история операций для отправления
        public readonly string $language = 'RUS', // Язык ответа
        public readonly ?string $login = null, // Логин (берется из конфига)
        public readonly ?string $password = null, // Пароль (берется из конфига)
    ) {
        //
    }

    /**
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'Barcode' => $this->barcode,
            'MessageType' => $this->messageType,
            'Language' => $this->language,
        ];
    }
}
