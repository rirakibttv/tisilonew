<?php

namespace App\Models;

use App\Enums\VendorListingItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class VendorListingItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_listing_id',
        'vendor_id',
        'product_variation_id',
        'variation_key',
        'seller_sku',
        'barcode',
        'purchase_price',
        'regular_price',
        'sale_price',
        'low_stock_threshold',
        'backorders_allowed',
        'status',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'low_stock_threshold' => 'integer',
            'backorders_allowed' => 'boolean',
            'status' => VendorListingItemStatus::class,
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $listing = VendorListing::query()->findOrFail($item->vendor_listing_id);

            $item->vendor_id = $listing->vendor_id;
            $item->variation_key = $item->product_variation_id
                ? 'variation:'.$item->product_variation_id
                : 'base';

            $duplicateSkuExists = self::query()
                ->where('vendor_id', $listing->vendor_id)
                ->where('seller_sku', $item->seller_sku)
                ->when(
                    $item->exists,
                    fn ($query) => $query->whereKeyNot($item->getKey()),
                )
                ->exists();

            if ($duplicateSkuExists) {
                throw ValidationException::withMessages([
                    'seller_sku' => 'This seller SKU is already used by the vendor.',
                ]);
            }

            if ($item->product_variation_id) {
                $belongsToProduct = ProductVariation::query()
                    ->whereKey($item->product_variation_id)
                    ->where('product_id', $listing->product_id)
                    ->exists();

                if (! $belongsToProduct) {
                    throw ValidationException::withMessages([
                        'product_variation_id' => 'The selected variation does not belong to this catalog product.',
                    ]);
                }
            }

            if (
                $item->sale_price !== null
                && (float) $item->sale_price > (float) $item->regular_price
            ) {
                throw ValidationException::withMessages([
                    'sale_price' => 'Sale price cannot be greater than regular price.',
                ]);
            }
        });

        static::saved(function (self $item): void {
            if (! $item->is_default) {
                return;
            }

            self::query()
                ->where('vendor_listing_id', $item->vendor_listing_id)
                ->whereKeyNot($item->getKey())
                ->update(['is_default' => false]);
        });
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class, 'vendor_listing_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        if ($this->relationLoaded('stocks')) {
            return $this->stocks->sum(
                fn (InventoryStock $stock): int => $stock->available_quantity,
            );
        }

        return (int) $this->stocks()
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as available')
            ->value('available');
    }
}
