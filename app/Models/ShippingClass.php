<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingClass extends Model
{
    protected $fillable = ['name', 'code', 'description', 'default_region_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRegionRate::class);
    }

    public function defaultRegion(): BelongsTo
    {
        return $this->belongsTo(ShippingRegion::class, 'default_region_id');
    }
}
