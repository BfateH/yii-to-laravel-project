<?php

namespace App\Modules\Tracking\Providers\RussianPost\DTOs\Responses;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * DTO для представления одного события (PostalOrderEvent) из ответа PostalOrderEventsForMail API Почты России.
 */
class PostalOrderEventResponseDTO
{
    public readonly string $number; // Number - Номер почтового перевода
    public readonly DateTimeInterface $eventDateTime; // EventDateTime - Дата и время операции
    public readonly int $eventType; // EventType - Код операции
    public readonly string $eventName; // EventName - Название операции
    public readonly ?string $indexTo; // IndexTo - Почтовый индекс получателя
    public readonly ?string $indexEvent; // IndexEvent - Почтовый индекс отделения операции
    public readonly ?int $sumPaymentForward; // SumPaymentForward - Сумма наложенного платежа в копейках
    public readonly ?string $countryEventCode; // CountryEventCode - Код страны операции
    public readonly ?string $countryToCode; // CountryToCode - Код страны получателя
    public readonly array $rawData;

    /**
     * @param array $postalOrderEventData Ассоциативный массив данных события из ответа API
     * @throws \Exception Если не удается распарсить дату
     */
    public function __construct(array $postalOrderEventData)
    {
        $this->number = (string)($postalOrderEventData['Number'] ?? '');
        $eventDateTimeString = $postalOrderEventData['EventDateTime'] ?? 'now';
        $this->eventDateTime = new DateTimeImmutable($eventDateTimeString);

        $this->eventType = (int)($postalOrderEventData['EventType'] ?? 0);
        $this->eventName = (string)($postalOrderEventData['EventName'] ?? '');

        $this->indexTo = !empty($postalOrderEventData['IndexTo']) ? (string)$postalOrderEventData['IndexTo'] : null;
        $this->indexEvent = !empty($postalOrderEventData['IndexEvent']) ? (string)$postalOrderEventData['IndexEvent'] : null;
        $this->sumPaymentForward = isset($postalOrderEventData['SumPaymentForward']) ? (int)$postalOrderEventData['SumPaymentForward'] : null;
        $this->countryEventCode = !empty($postalOrderEventData['CountryEventCode']) ? (string)$postalOrderEventData['CountryEventCode'] : null;
        $this->countryToCode = !empty($postalOrderEventData['CountryToCode']) ? (string)$postalOrderEventData['CountryToCode'] : null;

        $this->rawData = $postalOrderEventData;
    }

    /**
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'eventDateTime' => $this->eventDateTime->format(DateTimeInterface::ATOM), // Формат ISO 8601
            'eventType' => $this->eventType,
            'eventName' => $this->eventName,
            'indexTo' => $this->indexTo,
            'indexEvent' => $this->indexEvent,
            'sumPaymentForward' => $this->sumPaymentForward,
            'countryEventCode' => $this->countryEventCode,
            'countryToCode' => $this->countryToCode,
            'rawData' => $this->rawData,
        ];
    }
}
