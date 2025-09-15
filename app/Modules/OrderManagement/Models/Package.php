<?php

namespace App\Modules\OrderManagement\Models;

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
    ];
    protected $casts = [
        'status' => PackageStatus::class,
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
