<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorWarehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'name',
        'code',
        'contact_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'district',
        'upazila',
        'postal_code',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $warehouse): void {
            if (! $warehouse->is_default) {
                return;
            }

            self::query()
                ->where('vendor_id', $warehouse->vendor_id)
                ->whereKeyNot($warehouse->getKey())
                ->update(['is_default' => false]);
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
