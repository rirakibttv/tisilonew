<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BannerSlider extends Model
{
    protected $fillable = [
        'uuid',
        'slider_group_id',
        'name',
        'image',
        'destination_url',
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
        static::creating(function (self $slider): void {
            $slider->uuid ??= (string) Str::uuid();
            $slider->slider_group_id ??= SliderGroup::main()->getKey();

            if ($slider->sort_order === null) {
                $slider->sort_order = ((int) static::query()
                    ->where('slider_group_id', $slider->slider_group_id)
                    ->max('sort_order')) + 1;
            }
        });

        static::created(function (self $slider): void {
            if (blank($slider->name)) {
                $slider->forceFill([
                    'name' => 'Slider '.max(1, (int) $slider->sort_order),
                ])->saveQuietly();
            }
        });
    }

    /** @return BelongsTo<SliderGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SliderGroup::class, 'slider_group_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('image')
            ->where('image', '!=', '');
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/'.ltrim($this->image, '/'));
    }

    public function getDestinationHrefAttribute(): ?string
    {
        $destination = trim((string) $this->destination_url);

        if ($destination === '') {
            return null;
        }

        if ($destination === '#' || (str_starts_with($destination, '/') && ! str_starts_with($destination, '//'))) {
            return $destination;
        }

        return filter_var($destination, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($destination, PHP_URL_SCHEME)), ['http', 'https'], true)
                ? $destination
                : null;
    }
}
