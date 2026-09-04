<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image',
        'description',
        'status',
        'sort_order',
        'seo_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Canonical public category URL. Storefront category permalinks always end
     * with a slash to match the marketplace's chosen SEO URL structure.
     */
    protected function permalink(): Attribute
    {
        return Attribute::get(
            fn (): string => rtrim(route('store.categories.show', ['category' => $this->slug]), '/').'/',
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
