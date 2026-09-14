<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SliderGroup extends Model
{
    public const MAIN_PLACEMENT = 'main';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'placement',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            $group->uuid ??= (string) Str::uuid();
            $group->slug = Str::slug($group->slug ?: $group->name);

            if ($group->sort_order === null) {
                $group->sort_order = ((int) static::query()->max('sort_order')) + 1;
            }
        });
    }

    /** @return HasMany<BannerSlider, $this> */
    public function slides(): HasMany
    {
        return $this->hasMany(BannerSlider::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function main(): self
    {
        return static::query()->firstOrCreate(
            ['placement' => self::MAIN_PLACEMENT],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Main Slider',
                'slug' => 'main-slider',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    /** @return array<string, string> */
    public static function placementOptions(): array
    {
        return [
            self::MAIN_PLACEMENT => 'Main Slider (Homepage Hero)',
            'after_services' => 'After Service Highlights',
            'after_categories' => 'After Categories',
            'before_hot_deals' => 'Before Hot Deals',
            'after_hot_deals' => 'After Hot Deals',
            'before_footer' => 'Before Footer',
        ];
    }

    public function getPlacementLabelAttribute(): string
    {
        return static::placementOptions()[$this->placement] ?? Str::headline($this->placement);
    }
}
