<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FlashSale extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (FlashSale $flashSale): void {
            $flashSale->uuid ??= (string) Str::uuid();
        });
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function isLive(): bool
    {
        return $this->is_active
            && $this->starts_at?->lte(now())
            && $this->ends_at?->isFuture();
    }
}
