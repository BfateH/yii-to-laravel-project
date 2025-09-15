<?php

namespace App\Modules\Acquiring\Contracts;

use App\Models\Payment; // Используем стандартную модель
use App\Modules\Acquiring\Enums\PaymentStatus;

interface AcquirerInterface
{
    /**
     * Создать платеж.
     *
     * @param array $data Данные платежа (сумма, описание, order_id партнера и т.д.)
     * @param array $partnerConfig Конфигурация эквайринга партнера (расшифрованные данные)
     * @return array Массив с данными для редиректа или 3DS (например, ['redirect_url' => '...'] или ['form_data' => [...]])
     */
    public function createPayment(array $data, array $partnerConfig): array;

    /**
     * Обработать webhook-уведомление.
     *
     * @param array $payload Данные из webhook
     * @param array $partnerConfig Конфигурация эквайринга партнера (расшифрованные данные)
     * @return void
     */
    public function handleWebhook(array $payload, array $partnerConfig): void;

    /**
     * Оформить возврат.
     *
     * @param Payment $payment Модель платежа
     * @param float|null $amount Сумма возврата (null для полного)
     * @param array $partnerConfig Конфигурация эквайринга партнера (расшифрованные данные)
     * @return bool Успешность операции
     */
    public function refundPayment(Payment $payment, ?float $amount, array $partnerConfig): bool;

    /**
     * Получить статус платежа у провайдера.
     *
     * @param string $acquirerPaymentId Идентификатор платежа у провайдера
     * @param array $partnerConfig Конфигурация эквайринга партнера (расшифрованные данные)
     * @return PaymentStatus Статус платежа
     */
    public function getPaymentStatus(string $acquirerPaymentId, array $partnerConfig): PaymentStatus;
}
