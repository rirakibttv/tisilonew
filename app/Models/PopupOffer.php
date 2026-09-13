<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PopupOffer extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'description',
        'image',
        'link_url',
        'button_text',
        'footer_text',
        'is_active',
        'display_delay_seconds',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_delay_seconds' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $offer): void {
            $offer->uuid ??= (string) Str::uuid();
        });

        static::saved(function (self $offer): void {
            if ($offer->is_active) {
                static::query()
                    ->whereKeyNot($offer->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

        });
    }

    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('image')
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    public static function activeForStorefront(): ?self
    {
        return static::query()->currentlyVisible()->latest('updated_at')->first();
    }

    public function getImageUrlAttribute(): ?string
    {
        return filled($this->image) ? asset('storage/'.ltrim($this->image, '/')) : null;
    }
}
