<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\ParcelDTO;
use App\Modules\Providers\Shopogolic\DTO\ParcelItemDTO;
use App\Modules\Providers\Shopogolic\DTO\ServiceDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class ParcelsRequest extends AbstractAction
{
    /**
     * Получение списка посылок с фильтрацией.
     *
     * @param array $filters Фильтры: ['warehouse_id' => 123, 'user_id' => 456, 'status_id' => 500]
     * @return array Массив объектов ParcelDTO
     * @throws ShopogolicApiException
     */
    public function getFiltered(array $filters = []): array
    {
        $query = [];

        foreach (['warehouse_id', 'user_id', 'status_id'] as $filterKey) {
            if (isset($filters[$filterKey])) {
                $query["filter[{$filterKey}]"] = $filters[$filterKey];
            }
        }

        return $this->getAndMap('parcels', $query);
    }

    /**
     * Получение одной посылки по ID.
     *
     * @param int $parcelId
     * @return ParcelDTO|null
     * @throws ShopogolicApiException
     */
    public function getById(int $parcelId): ?ParcelDTO
    {
        try {
            $response = $this->client->get("parcels/{$parcelId}");
            $data = $response['data'] ?? $response;

            return $this->mapToDTO($data);
        } catch (ShopogolicApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Создание новой посылки.
     *
     * @param array $data Параметры посылки (warehouse_id, courier_id, user_id, weight, address и т.д.)
     * @return ParcelDTO
     * @throws ShopogolicApiException
     */
    public function create(array $data): ParcelDTO
    {
        $response = $this->client->post('parcels', $data);
        $responseData = $response['data'] ?? $response;

        return $this->mapToDTO($responseData);
    }

    /**
     * Редактирование существующей посылки.
     *
     * @param int $parcelId
     * @param array $data Параметры для обновления
     * @return ParcelDTO
     * @throws ShopogolicApiException
     */
    public function update(int $parcelId, array $data): ParcelDTO
    {
        $response = $this->client->put("parcels/{$parcelId}", $data);
        $responseData = $response['data'] ?? $response;

        return $this->mapToDTO($responseData);
    }

    /**
     * Оплата посылки.
     *
     * @param int $parcelId ID посылки.
     * @return ParcelDTO Посылка после оплаты.
     * @throws ShopogolicApiException
     */
    public function pay(int $parcelId): ParcelDTO
    {
        $response = $this->client->post("parcels/{$parcelId}/paid");
        $data = $response['data'] ?? $response;

        return $this->mapToDTO($data);
    }

    /**
     * Остановка обработки посылки.
     *
     * @param int $parcelId ID посылки.
     * @param int $holdReasonId ID причины остановки (100, 200, 300, 400, 500, 600).
     * @return ParcelDTO Посылка после остановки.
     * @throws ShopogolicApiException
     */
    public function hold(int $parcelId, int $holdReasonId): ParcelDTO
    {
        $requestData = [
            'hold_reason_id' => $holdReasonId,
        ];

        $response = $this->client->post("parcels/{$parcelId}/hold", $requestData);
        $data = $response['data'] ?? $response;

        return $this->mapToDTO($data);
    }

    /**
     * @param array $data Сырые данные посылки от API
     * @return ParcelDTO
     */
    protected function mapToDTO(array $data): ParcelDTO
    {
        $items = null;
        if (!empty($data['items']) && is_array($data['items'])) {
            $items = array_map(fn($itemData) => ParcelItemDTO::fromArray($itemData), $data['items']);
        }

        $services = null;
        if (!empty($data['services']) && is_array($data['services'])) {
            $services = array_map(fn($serviceData) => ServiceDTO::fromArray($serviceData), $data['services']);
        }

        return new ParcelDTO(
            (int) ($data['id'] ?? 0),
            $data['track'] ?? null,
            (int) ($data['warehouse_id'] ?? 0),
            (int) ($data['courier_id'] ?? 0),
            (int) ($data['user_id'] ?? 0),
            (int) ($data['address_id'] ?? 0),
            (int) ($data['status_id'] ?? 50),
            (string) ($data['status'] ?? 'Unknown'),
            (float) ($data['weight'] ?? 0.0),
            (float) ($data['length'] ?? 0.0),
            (float) ($data['width'] ?? 0.0),
            (float) ($data['height'] ?? 0.0),
            (int) ($data['insurance'] ?? 0),
            $data['comment'] ?? null,
            $data['date_created'] ?? null,
            $data['date_sent'] ?? null,
            $items,
            $services
        );
    }
}
