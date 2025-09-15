<?php

namespace App\Models;

use App\Modules\Acquiring\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'status',
        'acquirer_type',
        'acquirer_payment_id',
        'description',
        'order_id',
        'metadata',
        'webhook_log_id',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'float',
        'status' => PaymentStatus::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeByIdempotencyKey($query, string $key)
    {
        return $query->where('idempotency_key', $key);
    }

    public function scopeByAcquirerReference($query, string $acquirerPaymentId, string $acquirerType)
    {
        return $query->where('acquirer_payment_id', $acquirerPaymentId)
            ->where('acquirer_type', $acquirerType);
    }
}
