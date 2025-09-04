<?php

namespace App\Modules\OrderManagement\Models;

use App\Modules\OrderManagement\Enums\PackageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'status',
    ];
    protected $casts = [
        'status' => PackageStatus::class,
    ];

}
