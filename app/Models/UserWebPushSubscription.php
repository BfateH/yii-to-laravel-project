<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserWebPushSubscription extends Model
{
    use HasFactory;
    protected $table = 'user_webpush_subscriptions';



    protected $fillable = [
        'user_id',
        'endpoint',
        'p256dh',
        'auth'
    ];

    protected $casts = [
        'user_id' => 'integer'
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
