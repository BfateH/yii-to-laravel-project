<?php

namespace App\Modules\Acquiring\Contracts;

interface EncryptionInterface
{
    /**
     * Зашифровать данные.
     *
     * @param string $data Данные для шифрования.
     * @return string Зашифрованные данные (включая IV/тег аутентификации).
     */
    public function encrypt(string $data): string;

    /**
     * Расшифровать данные.
     *
     * @param string $encryptedData Зашифрованные данные.
     * @return string Расшифрованные данные.
     * @throws \Exception Если расшифровка не удалась (например, неверный тег аутентификации).
     */
    public function decrypt(string $encryptedData): string;

    /**
     * Получить уникальный идентификатор текущего ключа шифрования.
     * Используется для хранения метаданных о том, каким ключом зашифрованы данные.
     *
     * @return string Идентификатор ключа.
     */
    public function getCurrentKeyId(): string;
}
