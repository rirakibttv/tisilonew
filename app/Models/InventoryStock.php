<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class InventoryStock extends Model
{
    protected $fillable = [
        'vendor_listing_item_id',
        'vendor_warehouse_id',
        'quantity',
        'reserved_quantity',
        'incoming_quantity',
        'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'incoming_quantity' => 'integer',
            'reorder_point' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $stock): void {
            $item = VendorListingItem::query()->findOrFail($stock->vendor_listing_item_id);
            $warehouse = VendorWarehouse::query()->findOrFail($stock->vendor_warehouse_id);

            if ($item->vendor_id !== $warehouse->vendor_id) {
                throw ValidationException::withMessages([
                    'vendor_warehouse_id' => 'Inventory must be stored in a warehouse owned by the same vendor.',
                ]);
            }

            $duplicateExists = self::query()
                ->where('vendor_listing_item_id', $stock->vendor_listing_item_id)
                ->where('vendor_warehouse_id', $stock->vendor_warehouse_id)
                ->when(
                    $stock->exists,
                    fn ($query) => $query->whereKeyNot($stock->getKey()),
                )
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'vendor_warehouse_id' => 'This SKU already has an inventory record in the selected warehouse.',
                ]);
            }

            foreach (['quantity', 'reserved_quantity', 'incoming_quantity', 'reorder_point'] as $field) {
                if ((int) $stock->{$field} < 0) {
                    throw ValidationException::withMessages([
                        $field => 'Inventory quantities cannot be negative.',
                    ]);
                }
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VendorListingItem::class, 'vendor_listing_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(VendorWarehouse::class, 'vendor_warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }
}
