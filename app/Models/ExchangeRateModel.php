<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateModel extends Model
{
    use HasFactory;

    protected $table = 'exchange_rates';

    protected $fillable = [
        'base_currency_code',
        'target_currency_code',
        'rate',
        'date',
    ];

    protected $casts = [
        'rate' => 'decimal:10',
        'date' => 'date',
    ];
}
