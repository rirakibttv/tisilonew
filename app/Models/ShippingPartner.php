<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingPartner extends Model
{
    protected $fillable = [
        'name', 'code', 'contact_name', 'phone', 'email', 'tracking_url',
        'api_provider', 'estimated_min_days', 'estimated_max_days', 'notes', 'is_active',
        'inside_city_first_kg_charge', 'outside_city_first_kg_charge', 'additional_kg_charge',
    ];

    protected function casts(): array
    {
        return [
            'estimated_min_days' => 'integer',
            'estimated_max_days' => 'integer',
            'is_active' => 'boolean',
            'inside_city_first_kg_charge' => 'decimal:2',
            'outside_city_first_kg_charge' => 'decimal:2',
            'additional_kg_charge' => 'decimal:2',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRegionRate::class);
    }
}
