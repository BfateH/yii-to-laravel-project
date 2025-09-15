<?php

namespace App\Models;

use App\Modules\Acquiring\Contracts\EncryptionInterface;
use App\Modules\Acquiring\Enums\AcquirerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AcquirerConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'encrypted_credentials',
        'is_active',
    ];

    protected $casts = [
        'type' => AcquirerType::class,
        'is_active' => 'boolean',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Accessor для получения расшифрованных учетных данных
    protected function encryptedCredentials(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (?string $value) {
                if ($value === null) {
                    return null;
                }

                $encryptionService = app(EncryptionInterface::class);
                try {
                    return json_decode($encryptionService->decrypt($value), true);
                } catch (\Exception $e) {
                    Log::error("Failed to decrypt acquirer credentials for config ID {$this->id}: " . $e->getMessage());
                    return null;
                }
            },
            set: function (?array $value) {
                if ($value === null) {
                    return null;
                }

                $encryptionService = app(EncryptionInterface::class);
                try {
                    return $encryptionService->encrypt(json_encode($value));
                } catch (\Exception $e) {
                    Log::error("Failed to encrypt acquirer credentials for config ID {$this->id}: " . $e->getMessage());
                    throw $e;
                }
            },
        );
    }

    public function getDecryptedCredentials(): ?array
    {
        return $this->encrypted_credentials;
    }

    public function setCredentials(array $credentials): void
    {
        $this->encrypted_credentials = $credentials;
    }
}
