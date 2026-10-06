<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
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
        'show_on_homepage',
        'sort_order',
        'seo_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'show_on_homepage' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The shared storefront navigation tree used by every public page.
     *
     * @return Collection<int, self>
     */
    public static function storefrontNavigation(): Collection
    {
        return static::query()
            ->where('status', true)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->where('status', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'children.children' => fn ($query) => $query
                    ->where('status', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Canonical public category URL. All storefront paths use the same
     * trailing-slash-free format so internal navigation never needs a redirect.
     */
    protected function permalink(): Attribute
    {
        return Attribute::get(function (): string {
            $segments = explode('/', $this->hierarchicalPath());
            $categorySlug = array_shift($segments);

            return rtrim(route('store.categories.show', [
                'categorySlug' => $categorySlug,
                'categoryPath' => $segments === [] ? null : implode('/', $segments),
            ]), '/');
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

    /** @return array<int, self> */
    public function hierarchyForCatalog(): array
    {
        return $this->hierarchy();
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
