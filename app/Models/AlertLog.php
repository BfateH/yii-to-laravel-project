<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AlertLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'status',
        'error'
    ];

    protected $casts = [
        'alert_id' => 'integer'
    ];


    public function alert(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }
}
