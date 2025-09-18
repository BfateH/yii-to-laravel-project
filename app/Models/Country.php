<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}
