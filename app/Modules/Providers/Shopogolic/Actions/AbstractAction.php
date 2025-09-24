<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use App\Modules\Providers\Shopogolic\Http\Client;

abstract class AbstractAction
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
     * @param array $data Сырые данные от API
     * @return object|array Объект или массив DTO
     */
    abstract protected function mapToDTO(array $data): object|array;

    /**
     * @param array $items Массив сырых данных
     * @return array Массив DTO-объектов
     */
    protected function mapCollectionToDTOs(array $items): array
    {
        return array_map([$this, 'mapToDTO'], $items);
    }

    /**
     * @param string $uri
     * @param array $query
     * @return array
     * @throws ShopogolicApiException
     */
    protected function getAndMap(string $uri, array $query = []): array
    {
        $response = $this->client->get($uri, $query);

        $items = $response['data'] ?? $response;

        if (!is_array($items) || !array_is_list($items)) {
            $items = [$items];
        }

        return $this->mapCollectionToDTOs($items);
    }
}
