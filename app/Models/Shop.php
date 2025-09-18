<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\BelongsToMany;
use \Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shop extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'slug',
        'is_active',
        'is_with_vpn',
        'description',
        'link_to_the_store',
        'logo_preview',
        'popularity_index',
        'rating_index',
        'sort_index',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_with_vpn' => 'boolean',
    ];

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ShopCategory::class);
    }
}
