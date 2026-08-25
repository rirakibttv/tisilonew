<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\ProductCondition;
use App\Enums\VendorListingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class VendorListing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'product_id',
        'approved_by',
        'status',
        'condition',
        'fulfillment_type',
        'warranty',
        'min_order_quantity',
        'max_order_quantity',
        'handling_time_days',
        'commission_rate_override',
        'is_featured',
        'rejection_reason',
        'approved_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorListingStatus::class,
            'condition' => ProductCondition::class,
            'fulfillment_type' => FulfillmentType::class,
            'commission_rate_override' => 'decimal:2',
            'is_featured' => 'boolean',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $listing): void {
            $duplicateExists = self::query()
                ->where('vendor_id', $listing->vendor_id)
                ->where('product_id', $listing->product_id)
                ->when(
                    $listing->exists,
                    fn ($query) => $query->whereKeyNot($listing->getKey()),
                )
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'product_id' => 'This vendor already has a listing for the selected product.',
                ]);
            }

            if (
                $listing->max_order_quantity !== null
                && $listing->max_order_quantity < $listing->min_order_quantity
            ) {
                throw ValidationException::withMessages([
                    'max_order_quantity' => 'Maximum order quantity must be greater than or equal to the minimum.',
                ]);
            }

            if (
                $listing->status === VendorListingStatus::Approved
                && $listing->approved_at === null
            ) {
                $listing->approved_at = now();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorListingItem::class);
    }
}
