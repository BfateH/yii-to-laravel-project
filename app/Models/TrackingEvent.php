<?php

namespace App\Models;

use App\Modules\OrderManagement\Models\Package;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    use HasFactory;

    protected $table = 'tracking_events';

    protected $fillable = [
        'package_id',
        'operation_date',
        'operation_type_id',
        'operation_type_name',
        'operation_attr_id',
        'operation_attr_name',
        'operation_address_index',
        'operation_address_description',
        'destination_address_index',
        'destination_address_description',
        'country_oper_id',
        'country_oper_code2a',
        'country_oper_code3a',
        'country_oper_name_ru',
        'country_oper_name_en',
        'item_barcode',
        'item_mass',
        'payment',
        'value',
        'raw_data',
    ];

    protected $casts = [
        'operation_date' => 'datetime',
        'raw_data' => 'array',
        'item_mass' => 'integer',
        'payment' => 'integer',
        'value' => 'integer',
        'operation_type_id' => 'integer',
        'operation_attr_id' => 'integer',
        'country_oper_id' => 'integer',
    ];

    public function package(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
