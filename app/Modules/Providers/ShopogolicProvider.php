<?php

namespace App\Modules\Providers;

use App\Modules\Providers\Shopogolic\Actions\AddressesRequest;
use App\Modules\Providers\Shopogolic\Actions\CalculatorRequest;
use App\Modules\Providers\Shopogolic\Actions\CitiesRequest;
use App\Modules\Providers\Shopogolic\Actions\CountriesRequest;
use App\Modules\Providers\Shopogolic\Actions\CouriersRequest;
use App\Modules\Providers\Shopogolic\Actions\HsCodesRequest;
use App\Modules\Providers\Shopogolic\Actions\OrdersRequest;
use App\Modules\Providers\Shopogolic\Actions\ParcelsRequest;
use App\Modules\Providers\Shopogolic\Actions\RegionsRequest;
use App\Modules\Providers\Shopogolic\Actions\UsersRequest;
use App\Modules\Providers\Shopogolic\Actions\WarehousesRequest;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use App\Modules\Providers\Shopogolic\Http\Client;
use InvalidArgumentException;

class ShopogolicProvider implements ProviderInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Получение списка складов.
     *
     * @param array $filters
     * @return array Массив объектов WarehouseDTO
     * @throws ShopogolicApiException
     */
    public function getWarehouses(array $filters = []): array
    {
        $warehousesRequest = new WarehousesRequest($this->client);
        dump($warehousesRequest);
        dump($filters);
        return $warehousesRequest->getAll();
    }

    /**
     * Получение списка курьеров (способов доставки).
     *
     * @param array $filters Ожидается ['warehouse_id' => 123]
     * @return array Массив объектов CourierDTO
     * @throws ShopogolicApiException
     */
    public function getCouriers(array $filters = []): array
    {
        if (empty($filters['warehouse_id'])) {
            return [];
        }

        $couriersRequest = new CouriersRequest($this->client);
        return $couriersRequest->getByWarehouseId((int) $filters['warehouse_id']);
    }

    /**
     * Получение списка заказов.
     *
     * @param array $filters Фильтры: ['warehouse_id' => 123, 'user_id' => 456, 'status_id' => 500]
     * @return array Массив объектов OrderDTO
     * @throws ShopogolicApiException
     */
    public function getOrders(array $filters = []): array
    {
        $ordersRequest = new OrdersRequest($this->client);
        return $ordersRequest->getFiltered($filters);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function createOrder(array $orderData): \App\Modules\Providers\Shopogolic\DTO\OrderDTO
    {
        $ordersRequest = new OrdersRequest($this->client);
        return $ordersRequest->create($orderData);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function updateOrder(int $orderId, array $orderData): \App\Modules\Providers\Shopogolic\DTO\OrderDTO
    {
        $ordersRequest = new OrdersRequest($this->client);
        return $ordersRequest->update($orderId, $orderData);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function payOrder(int $orderId): \App\Modules\Providers\Shopogolic\DTO\OrderDTO
    {
        $ordersRequest = new OrdersRequest($this->client);
        return $ordersRequest->pay($orderId);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getOrderById(int $orderId): ?\App\Modules\Providers\Shopogolic\DTO\OrderDTO
    {
        $ordersRequest = new OrdersRequest($this->client);
        return $ordersRequest->getById($orderId);
    }

    /**
     * Получение списка посылок.
     *
     * @param array $filters Фильтры: ['warehouse_id' => 123, 'user_id' => 456, 'status_id' => 500]
     * @return array Массив объектов ParcelDTO
     * @throws ShopogolicApiException
     */
    public function getParcels(array $filters = []): array
    {
        $parcelsRequest = new ParcelsRequest($this->client);
        return $parcelsRequest->getFiltered($filters);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function payParcel(int $parcelId): \App\Modules\Providers\Shopogolic\DTO\ParcelDTO
    {
        $parcelsRequest = new ParcelsRequest($this->client);
        return $parcelsRequest->pay($parcelId);
    }


    /**
     * @throws ShopogolicApiException
     */
    public function holdParcel(int $parcelId, int $holdReasonId): \App\Modules\Providers\Shopogolic\DTO\ParcelDTO
    {
        $parcelsRequest = new ParcelsRequest($this->client);
        return $parcelsRequest->hold($parcelId, $holdReasonId);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getParcelById(int $parcelId): ?\App\Modules\Providers\Shopogolic\DTO\ParcelDTO
    {
        $parcelsRequest = new ParcelsRequest($this->client);
        return $parcelsRequest->getById($parcelId);
    }

    /**
     * Расчёт стоимости доставки.
     *
     * @param array $params Параметры: warehouse_id, weight, country_code, [length, width, height]
     * @return array Массив с одним объектом CalculationResultDTO (для унификации интерфейса)
     * @throws InvalidArgumentException|ShopogolicApiException если не переданы обязательные параметры
     */
    public function calculateShipping(array $params): array
    {
        $calculatorRequest = new CalculatorRequest($this->client);
        $result = $calculatorRequest->calculate($params);
        return [$result];
    }

    /**
     * @throws ShopogolicApiException
     */
    public function createUser(array $userData): \App\Modules\Providers\Shopogolic\DTO\UserDTO
    {
        $usersRequest = new UsersRequest($this->client);
        return $usersRequest->create($userData);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function updateUser(int $userId, array $userData): \App\Modules\Providers\Shopogolic\DTO\UserDTO
    {
        $usersRequest = new UsersRequest($this->client);
        return $usersRequest->update($userId, $userData);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getUsers(array $filters = []): array
    {
        $usersRequest = new UsersRequest($this->client);
        return $usersRequest->getAll($filters);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getUserById(int $userId): ?\App\Modules\Providers\Shopogolic\DTO\UserDTO
    {
        $usersRequest = new UsersRequest($this->client);
        return $usersRequest->getById($userId);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function createAddress(array $addressData): \App\Modules\Providers\Shopogolic\DTO\AddressDTO
    {
        $addressesRequest = new AddressesRequest($this->client);
        return $addressesRequest->create($addressData);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function updateAddress(int $addressId, array $addressData): \App\Modules\Providers\Shopogolic\DTO\AddressDTO
    {
        $addressesRequest = new AddressesRequest($this->client);
        return $addressesRequest->update($addressId, $addressData);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getAddresses(array $filters = [], array $expand = []): array
    {
        $addressesRequest = new AddressesRequest($this->client);
        return $addressesRequest->getAll($filters, $expand);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getAddressById(int $addressId, array $expand = []): ?\App\Modules\Providers\Shopogolic\DTO\AddressDTO
    {
        $addressesRequest = new AddressesRequest($this->client);
        return $addressesRequest->getById($addressId, $expand);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function deleteAddress(int $addressId): bool
    {
        $addressesRequest = new AddressesRequest($this->client);
        return $addressesRequest->delete($addressId);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getCountries(int $perPage = 20, int $page = 1): array
    {
        $countriesRequest = new CountriesRequest($this->client);
        return $countriesRequest->getAll($perPage, $page);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getRegions(array $filters = [], array $expand = [], int $page = 1): array
    {
        $regionsRequest = new RegionsRequest($this->client);
        return $regionsRequest->getAll($filters, $expand, $page);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getCities(array $filters = [], array $expand = [], int $page = 1): array
    {
        $citiesRequest = new CitiesRequest($this->client);
        return $citiesRequest->getAll($filters, $expand, $page);
    }

    /**
     * @throws ShopogolicApiException
     */
    public function getHsCodes(int $perPage = 20, int $page = 1): array
    {
        $hsCodesRequest = new HsCodesRequest($this->client);
        return $hsCodesRequest->getAll($perPage, $page);
    }
}
