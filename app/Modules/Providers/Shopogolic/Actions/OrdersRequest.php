<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\ItemDTO;
use App\Modules\Providers\Shopogolic\DTO\OrderDTO;
use App\Modules\Providers\Shopogolic\DTO\ServiceDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class OrdersRequest extends AbstractAction
{
    /**
     * Получение списка заказов с фильтрацией.
     *
     * @param array $filters Фильтры: ['warehouse_id' => 123, 'user_id' => 456, 'status_id' => 500]
     * @return array Массив объектов OrderDTO
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

        // $query['page'] = $filters['page'] ?? 1;
        // $query['per-page'] = $filters['per-page'] ?? 20;

        return $this->getAndMap('/orders', $query);
    }

    /**
     * Получение одного заказа по ID.
     *
     * @param int $orderId
     * @return OrderDTO|null
     * @throws ShopogolicApiException
     */
    public function getById(int $orderId): ?OrderDTO
    {
        try {
            $response = $this->client->get("/orders/{$orderId}");
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
     * Создание нового заказа.
     *
     * @param array $orderData Данные заказа (warehouse_id, user_id, track и т.д.)
     * @return OrderDTO Созданный заказ.
     * @throws ShopogolicApiException
     */
    public function create(array $orderData): OrderDTO
    {
        $response = $this->client->post('/orders', $orderData);
        $data = $response['data'] ?? $response;

        return $this->mapToDTO($data);
    }

    /**
     * Редактирование существующего заказа.
     *
     * @param int $orderId ID заказа.
     * @param array $orderData Данные для обновления.
     * @return OrderDTO Обновленный заказ.
     * @throws ShopogolicApiException
     */
    public function update(int $orderId, array $orderData): OrderDTO
    {
        $response = $this->client->put("/orders/{$orderId}", $orderData);
        $data = $response['data'] ?? $response;

        return $this->mapToDTO($data);
    }

    /**
     * Оплата заказа.
     *
     * @param int $orderId ID заказа.
     * @return OrderDTO Заказ после оплаты.
     * @throws ShopogolicApiException
     */
    public function pay(int $orderId): OrderDTO
    {
        $response = $this->client->post("/orders/{$orderId}/paid");
        $data = $response['data'] ?? $response;

        return $this->mapToDTO($data);
    }

    /**
     * @param array $data Сырые данные заказа от API
     * @return OrderDTO
     */
    protected function mapToDTO(array $data): OrderDTO
    {

        $items = null;
        if (!empty($data['items']) && is_array($data['items'])) {
            $items = array_map(fn($itemData) => ItemDTO::fromArray($itemData), $data['items']);
        }

        $services = null;
        if (!empty($data['services']) && is_array($data['services'])) {
            $services = array_map(fn($serviceData) => ServiceDTO::fromArray($serviceData), $data['services']);
        }

        return new OrderDTO(
            (int) ($data['id'] ?? 0),
            $data['name'] ?? null,
            (int) ($data['warehouse_id'] ?? 0),
            (int) ($data['user_id'] ?? 0),
            (int) ($data['status_id'] ?? 100),
            (string) ($data['status'] ?? 'Unknown'),
            $data['date_created'] ?? null,
            $data['date_received'] ?? null,
            $data['track'] ?? null,
            $data['shop_name'] ?? null,
            $items,
            $services
        );
    }
}
