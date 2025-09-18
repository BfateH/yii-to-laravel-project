<?php

namespace App\Modules\OrderManagement\Models;

use App\Models\TrackingEvent;
use App\Models\User;
use App\Modules\OrderManagement\Enums\PackageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'status',
        'user_id',
        'tracking_number',
        'postal_order_events_data',
        'last_tracking_error',
        'last_tracking_error_type',
        'last_tracking_update',
    ];

    protected $casts = [
        'status' => PackageStatus::class,
        'postal_order_events_data' => 'array',
        'last_tracking_update' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trackingEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrackingEvent::class, 'package_id');
    }
}
