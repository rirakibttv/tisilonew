<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LandingPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'header_title',
        'slug',
        'status',
        'hero_badge',
        'headline',
        'subheadline',
        'cta_text',
        'hero_image',
        'theme_color',
        'offer_title',
        'offer_body',
        'trust_title',
        'benefits',
        'gallery_images',
        'reviews',
        'faqs',
        'video_url',
        'countdown_ends_at',
        'meta_title',
        'meta_description',
        'og_image',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'gallery_images' => 'array',
            'reviews' => 'array',
            'faqs' => 'array',
            'countdown_ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $landingPage): void {
            $landingPage->slug = Str::slug($landingPage->slug ?: $landingPage->name);

            if ($landingPage->status === 'published' && ! $landingPage->published_at) {
                $landingPage->published_at = now();
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $published): Builder => $published
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('products.id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('store.landing.show', $this);
    }

    public function getVideoEmbedUrlAttribute(): ?string
    {
        if (! $this->video_url) {
            return null;
        }

        $parts = parse_url($this->video_url);
        $host = strtolower($parts['host'] ?? '');
        $videoId = null;

        if (str_contains($host, 'youtu.be')) {
            $videoId = trim($parts['path'] ?? '', '/');
        } elseif (str_contains($host, 'youtube.com')) {
            parse_str($parts['query'] ?? '', $query);
            $videoId = $query['v'] ?? null;
            if (! $videoId && preg_match('~/embed/([A-Za-z0-9_-]{11})~', $parts['path'] ?? '', $matches)) {
                $videoId = $matches[1];
            }
        }

        return is_string($videoId) && preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)
            ? 'https://www.youtube-nocookie.com/embed/'.$videoId
            : null;
    }
}
