<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\UserDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class UsersRequest extends AbstractAction
{
    /**
     * Создание нового пользователя.
     *
     * @param array $userData Данные пользователя: email, password, firstname, lastname и т.д.
     * @return UserDTO Созданный пользователь.
     * @throws ShopogolicApiException
     */
    public function create(array $userData): UserDTO
    {
        $response = $this->client->post('/users', $userData);
        return $this->mapToDTO($response);
    }

    /**
     * Редактирование существующего пользователя.
     *
     * @param int $userId ID пользователя.
     * @param array $userData Данные для обновления.
     * @return UserDTO Обновленный пользователь.
     * @throws ShopogolicApiException
     */
    public function update(int $userId, array $userData): UserDTO
    {
        $response = $this->client->put("/users/{$userId}", $userData);
        return $this->mapToDTO($response);
    }

    /**
     * Получение списка пользователей с фильтрацией.
     *
     * @param array $filters Фильтры, например: ['email' => 'user@example.com']
     * @return array Массив объектов UserDTO.
     * @throws ShopogolicApiException
     */
    public function getAll(array $filters = []): array
    {
        return $this->getAndMap('/users', $filters);
    }

    /**
     * Получение одного пользователя по ID.
     *
     * @param int $userId ID пользователя.
     * @return UserDTO|null Пользователь или null, если не найден.
     * @throws ShopogolicApiException
     */
    public function getById(int $userId): ?UserDTO
    {
        try {
            $response = $this->client->get("/users/{$userId}");
            return $this->mapToDTO($response);
        } catch (ShopogolicApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    protected function mapToDTO(array $data): UserDTO
    {
        return new UserDTO(
            (int) ($data['id'] ?? 0),
            (string) ($data['email'] ?? ''),
            $data['external_id'] ?? null,
            (string) ($data['name'] ?? ''),
            $data['firstname'] ?? null,
            $data['lastname'] ?? null,
            $data['midname'] ?? null,
            $data['phone'] ?? null,
            $data['language'] ?? null,
            $data['country_code'] ?? null
        );
    }
}
