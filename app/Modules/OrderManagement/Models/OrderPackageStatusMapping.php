<?php

namespace App\Modules\OrderManagement\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPackageStatusMapping extends Model
{
    protected $table = 'om_order_package_mappings';
    protected $fillable = [
        'internal_status',
        'external_status'
    ];
}
