<?php

namespace App\Modules\Acquiring\Services;

use App\Modules\Acquiring\Contracts\EncryptionInterface;
use Illuminate\Support\Facades\Log;

class EncryptionService implements EncryptionInterface
{
    // TODO: В дальнейшем ключ будет браться из KMS
    // Пока используем ключ из .env
    private string $key;
    private string $cipher = 'aes-256-gcm'; // Рекомендуемый режим с аутентификацией

    public function __construct()
    {
        // Получаем ключ из .env. Длина должна быть 32 байта для AES-256.
        // openssl_random_pseudo_bytes(32)
        $keyBase64 = env('ACQUIRING_ENCRYPTION_KEY');
        if (empty($keyBase64)) {
            Log::critical('ACQUIRING_ENCRYPTION_KEY is not set in .env');
            throw new \InvalidArgumentException('ACQUIRING_ENCRYPTION_KEY is not set in .env');
        }
        $this->key = base64_decode($keyBase64);
        if (strlen($this->key) !== 32) {
            Log::critical('Invalid encryption key length for Acquiring Encryption Service. Key must be 32 bytes for AES-256-GCM.');
            throw new \InvalidArgumentException('Invalid encryption key length. Key must be 32 bytes.');
        }
    }

    public function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        if ($ivLength === false) {
            Log::error('Failed to get IV length for cipher: ' . $this->cipher);
            throw new \RuntimeException('Failed to get IV length.');
        }
        $iv = openssl_random_pseudo_bytes($ivLength);

        $tag = '';
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            $error = openssl_error_string();
            Log::error('Encryption failed: ' . ($error ?: 'Unknown error'));
            throw new \RuntimeException('Encryption failed.');
        }

        // Сохраняем IV и тег аутентификации вместе с данными
        // Формат: [key_id_length][key_id][iv][tag][encrypted_data]
        $keyId = $this->getCurrentKeyId();
        $keyIdLength = pack('n', strlen($keyId)); // 2 байта для длины ID ключа

        return base64_encode($keyIdLength . $keyId . $iv . $tag . $encrypted);
    }

    public function decrypt(string $encryptedData): string
    {
        if (empty($encryptedData)) {
            return '';
        }

        $data = base64_decode($encryptedData);
        if ($data === false) {
            Log::error('Base64 decoding failed for encrypted data.');
            throw new \RuntimeException('Invalid encrypted data format.');
        }

        // Извлекаем длину ID ключа (2 байта)
        $keyIdLengthBin = substr($data, 0, 2);
        if (strlen($keyIdLengthBin) < 2) {
            Log::error('Invalid encrypted data format: missing key ID length.');
            throw new \RuntimeException('Invalid encrypted data format.');
        }
        $keyIdLength = unpack('n', $keyIdLengthBin)[1];

        // Извлекаем ID ключа
        $keyId = substr($data, 2, $keyIdLength);
        if (strlen($keyId) !== $keyIdLength) {
            Log::error('Invalid encrypted data format: incomplete key ID.');
            throw new \RuntimeException('Invalid encrypted data format.');
        }

        // Проверка ключа (в упрощенном виде)
        // В реальной системе KMS здесь будет логика получения правильного ключа по ID
        if ($keyId !== $this->getCurrentKeyId()) {
            Log::warning("Attempting to decrypt data with key ID '{$keyId}', but current key ID is '{$this->getCurrentKeyId()}'. This might indicate key rotation.");
            // В реальной системе мы бы получили старый ключ из KMS по его ID
            // throw new \RuntimeException("Key mismatch during decryption.");
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        if ($ivLength === false) {
            Log::error('Failed to get IV length for cipher: ' . $this->cipher);
            throw new \RuntimeException('Failed to get IV length.');
        }
        $tagLength = 16; // Для GCM тег аутентификации всегда 16 байт

        // Извлекаем IV, тег и зашифрованные данные
        $iv = substr($data, 2 + $keyIdLength, $ivLength);
        $tag = substr($data, 2 + $keyIdLength + $ivLength, $tagLength);
        $encrypted = substr($data, 2 + $keyIdLength + $ivLength + $tagLength);

        if (strlen($iv) !== $ivLength || strlen($tag) !== $tagLength) {
            Log::error('Invalid encrypted data format: incomplete IV or tag.');
            throw new \RuntimeException('Invalid encrypted data format.');
        }

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            $error = openssl_error_string();
            Log::error("Decryption failed: {$error}");
            // Это может быть из-за неверного ключа или поврежденных данных/тега
            throw new \RuntimeException('Decryption failed. Data might be corrupted or key is incorrect.');
        }

        return $decrypted;
    }

    public function getCurrentKeyId(): string
    {
        // TODO: В интеграции с KMS это будет реальный ID ключа
        // Пока используем хеш от ключа как идентификатор
        return hash('sha256', $this->key);
    }
}
