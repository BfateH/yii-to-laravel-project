<?php

namespace App\Modules\Providers;

interface ProviderInterface
{
    /**
     * Получение списка складов
     *
     * @param array $filters
     * @return array Массив объектов WarehouseDTO
     */
    public function getWarehouses(array $filters = []): array;

    /**
     * Получение списка курьеров (способов доставки)
     *
     * @param array $filters
     * @return array Массив объектов CourierDTO
     */
    public function getCouriers(array $filters = []): array;

    /**
     * Получение списка заказов
     *
     * @param array $filters
     * @return array Массив объектов OrderDTO
     */
    public function getOrders(array $filters = []): array;

    /**
     * Создание нового заказа.
     *
     * @param array $orderData
     * @return \App\Modules\Providers\Shopogolic\DTO\OrderDTO
     */
    public function createOrder(array $orderData): \App\Modules\Providers\Shopogolic\DTO\OrderDTO;

    /**
     * Редактирование существующего заказа.
     *
     * @param int $orderId
     * @param array $orderData
     * @return \App\Modules\Providers\Shopogolic\DTO\OrderDTO
     */
    public function updateOrder(int $orderId, array $orderData): \App\Modules\Providers\Shopogolic\DTO\OrderDTO;

    /**
     * Оплата заказа.
     *
     * @param int $orderId
     * @return \App\Modules\Providers\Shopogolic\DTO\OrderDTO
     */
    public function payOrder(int $orderId): \App\Modules\Providers\Shopogolic\DTO\OrderDTO;

    /**
     * Получение заказа по ID.
     *
     * @param int $orderId
     * @return \App\Modules\Providers\Shopogolic\DTO\OrderDTO|null
     */
    public function getOrderById(int $orderId): ?\App\Modules\Providers\Shopogolic\DTO\OrderDTO;

    /**
     * Получение списка посылок
     *
     * @param array $filters
     * @return array Массив объектов ParcelDTO
     */
    public function getParcels(array $filters = []): array;

    /**
     * Оплата посылки.
     *
     * @param int $parcelId
     * @return \App\Modules\Providers\Shopogolic\DTO\ParcelDTO
     */
    public function payParcel(int $parcelId): \App\Modules\Providers\Shopogolic\DTO\ParcelDTO;

    /**
     * Остановка обработки посылки.
     *
     * @param int $parcelId
     * @param int $holdReasonId
     * @return \App\Modules\Providers\Shopogolic\DTO\ParcelDTO
     */
    public function holdParcel(int $parcelId, int $holdReasonId): \App\Modules\Providers\Shopogolic\DTO\ParcelDTO;

    /**
     * Получение посылки по ID.
     *
     * @param int $parcelId
     * @return \App\Modules\Providers\Shopogolic\DTO\ParcelDTO|null
     */
    public function getParcelById(int $parcelId): ?\App\Modules\Providers\Shopogolic\DTO\ParcelDTO;

    /**
     * Расчёт стоимости доставки
     *
     * @param array $params Параметры расчёта: warehouse_id, weight, country_code и т.д.
     * @return array Массив объектов CalculationResultDTO (или структурированный результат)
     */
    public function calculateShipping(array $params): array;

    /**
     * Создание нового пользователя.
     *
     * @param array $userData
     * @return \App\Modules\Providers\Shopogolic\DTO\UserDTO
     */
    public function createUser(array $userData): \App\Modules\Providers\Shopogolic\DTO\UserDTO;

    /**
     * Редактирование существующего пользователя.
     *
     * @param int $userId
     * @param array $userData
     * @return \App\Modules\Providers\Shopogolic\DTO\UserDTO
     */
    public function updateUser(int $userId, array $userData): \App\Modules\Providers\Shopogolic\DTO\UserDTO;

    /**
     * Получение списка пользователей.
     *
     * @param array $filters
     * @return array Массив объектов UserDTO
     */
    public function getUsers(array $filters = []): array;

    /**
     * Получение пользователя по ID.
     *
     * @param int $userId
     * @return \App\Modules\Providers\Shopogolic\DTO\UserDTO|null
     */
    public function getUserById(int $userId): ?\App\Modules\Providers\Shopogolic\DTO\UserDTO;

    /**
     * Создание нового адреса.
     *
     * @param array $addressData
     * @return \App\Modules\Providers\Shopogolic\DTO\AddressDTO
     */
    public function createAddress(array $addressData): \App\Modules\Providers\Shopogolic\DTO\AddressDTO;

    /**
     * Редактирование существующего адреса.
     *
     * @param int $addressId
     * @param array $addressData
     * @return \App\Modules\Providers\Shopogolic\DTO\AddressDTO
     */
    public function updateAddress(int $addressId, array $addressData): \App\Modules\Providers\Shopogolic\DTO\AddressDTO;

    /**
     * Получение списка адресов.
     *
     * @param array $filters
     * @param array $expand
     * @return array Массив объектов AddressDTO
     */
    public function getAddresses(array $filters = [], array $expand = []): array;

    /**
     * Получение адреса по ID.
     *
     * @param int $addressId
     * @param array $expand
     * @return \App\Modules\Providers\Shopogolic\DTO\AddressDTO|null
     */
    public function getAddressById(int $addressId, array $expand = []): ?\App\Modules\Providers\Shopogolic\DTO\AddressDTO;

    /**
     * Удаление адреса.
     *
     * @param int $addressId
     * @return bool
     */
    public function deleteAddress(int $addressId): bool;

    /**
     * Получение списка стран.
     *
     * @param int $perPage
     * @param int $page
     * @return array Массив объектов CountryDTO
     */
    public function getCountries(int $perPage = 20, int $page = 1): array;

    /**
     * Получение списка регионов.
     *
     * @param array $filters
     * @param array $expand
     * @param int $page
     * @return array Массив объектов RegionDTO
     */
    public function getRegions(array $filters = [], array $expand = [], int $page = 1): array;

    /**
     * Получение списка городов.
     *
     * @param array $filters
     * @param array $expand
     * @param int $page
     * @return array Массив объектов CityDTO
     */
    public function getCities(array $filters = [], array $expand = [], int $page = 1): array;

    /**
     * Получение списка таможенных кодов.
     *
     * @param int $perPage
     * @param int $page
     * @return array Массив объектов HsCodeDTO
     */
    public function getHsCodes(int $perPage = 20, int $page = 1): array;
}
