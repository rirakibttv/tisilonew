<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRegionRate extends Model
{
    protected $fillable = [
        'shipping_region_id', 'shipping_class_id', 'shipping_partner_id',
        'base_charge', 'additional_item_charge', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_charge' => 'decimal:2',
            'additional_item_charge' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(ShippingRegion::class, 'shipping_region_id');
    }

    public function shippingClass(): BelongsTo
    {
        return $this->belongsTo(ShippingClass::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(ShippingPartner::class, 'shipping_partner_id');
    }
}
