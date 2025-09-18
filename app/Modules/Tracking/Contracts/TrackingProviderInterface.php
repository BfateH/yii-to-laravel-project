<?php

namespace App\Modules\Tracking\Contracts;

use App\Modules\OrderManagement\Models\Package;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\OperationHistoryResponseDTO;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\PostalOrderEventResponseDTO;

interface TrackingProviderInterface
{
    /**
     * Получить историю операций по трек-номеру посылки.
     *
     * @param Package $package Модель посылки
     * @return array<int, OperationHistoryResponseDTO> Массив событий истории операций
     * @throws \Exception В случае ошибок API, сети, авторизации и т.д.
     */
    public function getTrackingHistory(Package $package): array;

    /**
     * Получить информацию об операциях с наложенным платежом по трек-номеру посылки.
     *
     * @param Package $package Модель посылки
     * @return array<int, PostalOrderEventResponseDTO> Массив событий наложенного платежа
     * @throws \Exception В случае ошибок API, сети, авторизации и т.д.
     */
    public function getPostalOrderEvents(Package $package): array;

    /**
     * Получить уникальный идентификатор провайдера.
     *
     * @return string
     */
    public function getIdentifier(): string;
}
