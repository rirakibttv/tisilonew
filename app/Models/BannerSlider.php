<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BannerSlider extends Model
{
    protected $fillable = [
        'uuid',
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

            if ($slider->sort_order === null) {
                $slider->sort_order = ((int) static::query()->max('sort_order')) + 1;
            }
        });

        static::created(function (self $slider): void {
            if (blank($slider->name)) {
                $slider->forceFill(['name' => 'Slider '.$slider->getKey()])->saveQuietly();
            }
        });
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
