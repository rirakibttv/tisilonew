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
        return Attribute::get(function (): string {
            $segments = explode('/', $this->hierarchicalPath());
            $categorySlug = array_shift($segments);

            return rtrim(route('store.categories.show', [
                'categorySlug' => $categorySlug,
                'categoryPath' => $segments === [] ? null : implode('/', $segments),
            ]), '/').'/';
        });
    }

    public function hierarchicalPath(): string
    {
        return collect($this->hierarchy())
            ->pluck('slug')
            ->implode('/');
    }

    public function hierarchicalName(): string
    {
        return collect($this->hierarchy())
            ->pluck('name')
            ->implode(' › ');
    }

    /** @return array<int, self> */
    private function hierarchy(): array
    {
        $hierarchy = [];
        $visited = [];
        $category = $this;

        while ($category instanceof self && ! isset($visited[$category->getKey()])) {
            $visited[$category->getKey()] = true;
            array_unshift($hierarchy, $category);
            $category = $category->parent;
        }

        return $hierarchy;
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
