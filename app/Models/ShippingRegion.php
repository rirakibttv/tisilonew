<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingRegion extends Model
{
    protected $fillable = [
        'division', 'district', 'upazila', 'postal_code', 'location_key',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $region): void {
            if (! filled($region->location_key) || $region->isDirty(['division', 'district', 'upazila'])) {
                $location = implode('|', [$region->division, $region->district, $region->upazila]);
                $region->location_key = Str::slug(str_replace('|', '-', $location)) ?: sha1(mb_strtolower($location));
            }

            if (self::query()->where('location_key', $region->location_key)
                ->when($region->exists, fn (Builder $query): Builder => $query->where($region->getKeyName(), '!=', $region->getKey()))
                ->exists()) {
                throw ValidationException::withMessages([
                    'upazila' => 'এই Division, District এবং Upazila/থানা ইতোমধ্যে যোগ করা হয়েছে।',
                ]);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRegionRate::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->division, $this->district, $this->upazila])->filter()->join(' › ');
    }
}
