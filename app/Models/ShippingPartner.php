<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingPartner extends Model
{
    protected $fillable = [
        'name', 'code', 'contact_name', 'phone', 'email', 'tracking_url',
        'api_provider', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRegionRate::class);
    }
}
