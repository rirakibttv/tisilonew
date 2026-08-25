<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DeploymentDataSnapshot
{
    public const DEFAULT_PATH = 'database/data/deployable-catalog.json';

    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            'version' => 1,
            'brands' => Brand::query()->orderBy('slug')->get()->map(fn (Brand $brand): array => Arr::only(
                $brand->toArray(),
                ['name', 'slug', 'logo', 'description', 'website', 'status', 'sort_order', 'seo_title', 'meta_description'],
            ))->all(),
            'categories' => Category::query()->with('parent:id,slug')->orderBy('slug')->get()->map(fn (Category $category): array => [
                ...Arr::only(
                    $category->toArray(),
                    ['name', 'slug', 'image', 'description', 'status', 'sort_order', 'seo_title', 'meta_description'],
                ),
                'parent_slug' => $category->parent?->slug,
            ])->all(),
            'attributes' => Attribute::query()->with(['values' => fn ($query) => $query->orderBy('slug')])->orderBy('slug')->get()
                ->map(fn (Attribute $attribute): array => [
                    ...Arr::only($attribute->toArray(), ['name', 'slug', 'type', 'sort_order', 'status']),
                    'values' => $attribute->values->map(fn (AttributeValue $value): array => Arr::only(
                        $value->toArray(),
                        ['value', 'slug', 'color_code', 'sort_order', 'status'],
                    ))->all(),
                ])->all(),
            'product_tags' => ProductTag::query()->orderBy('slug')->get()->map(fn (ProductTag $tag): array => Arr::only(
                $tag->toArray(),
                ['name', 'slug', 'description', 'sort_order', 'status', 'seo_title', 'meta_description'],
            ))->all(),
            'users' => User::query()->orderBy('email')->get()->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'password_hash' => $user->getRawOriginal('password'),
                'role' => $user->role->value,
                'status' => $user->status->value,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
            ])->all(),
            'products' => Product::query()
                ->with([
                    'brand:id,slug',
                    'category:id,slug',
                    'tags:id,slug',
                    'attributes:id,slug',
                    'variations' => fn ($query) => $query->whereNotNull('sku')->orderBy('sku'),
                    'variations.attributeValues.attribute:id,slug',
                ])
                ->orderBy('slug')
                ->get()
                ->map(fn (Product $product): array => [
                    ...Arr::only($product->toArray(), [
                        'name', 'slug', 'product_type', 'sku', 'barcode', 'regular_price', 'sale_price',
                        'purchase_price', 'manage_stock', 'stock_quantity', 'low_stock_threshold', 'stock_status',
                        'short_description', 'description', 'featured_image', 'gallery_images', 'weight',
                        'length', 'width', 'height', 'status', 'featured', 'sort_order', 'seo_title',
                        'meta_description', 'meta_keywords',
                    ]),
                    'brand_slug' => $product->brand?->slug,
                    'category_slug' => $product->category?->slug,
                    'tag_slugs' => $product->tags->pluck('slug')->sort()->values()->all(),
                    'attribute_slugs' => $product->attributes->pluck('slug')->sort()->values()->all(),
                    'variations' => $product->variations->map(fn (ProductVariation $variation): array => [
                        ...Arr::only($variation->toArray(), [
                            'sku', 'barcode', 'purchase_price', 'regular_price', 'sale_price',
                            'stock_quantity', 'low_stock_threshold', 'stock_status', 'image', 'weight',
                            'status', 'is_default', 'sort_order',
                        ]),
                        'attribute_values' => $variation->attributeValues
                            ->map(fn (AttributeValue $value): string => $value->attribute->slug.':'.$value->slug)
                            ->sort()
                            ->values()
                            ->all(),
                    ])->all(),
                ])->all(),
        ];
    }

    public function export(?string $path = null): string
    {
        $path = $this->absolutePath($path ?? self::DEFAULT_PATH);
        File::ensureDirectoryExists(dirname($path));
        $json = json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        if (File::exists($path) && File::get($path) === $json) {
            return $path;
        }

        File::put($path, $json);

        return $path;
    }

    /** @return array<string, int> */
    public function import(?string $path = null): array
    {
        $path = $this->absolutePath($path ?? self::DEFAULT_PATH);
        if (! File::exists($path)) {
            return [];
        }

        $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (($snapshot['version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported deployment data snapshot version.');
        }

        return DB::transaction(function () use ($snapshot): array {
            $counts = ['brands' => 0, 'categories' => 0, 'attributes' => 0, 'attribute_values' => 0, 'product_tags' => 0, 'users' => 0, 'products' => 0, 'product_variations' => 0];

            foreach ($snapshot['brands'] ?? [] as $data) {
                Brand::query()->updateOrCreate(['slug' => $data['slug']], $data);
                $counts['brands']++;
            }

            foreach ($snapshot['categories'] ?? [] as $data) {
                Category::query()->updateOrCreate(['slug' => $data['slug']], Arr::except($data, ['parent_slug']));
                $counts['categories']++;
            }
            foreach ($snapshot['categories'] ?? [] as $data) {
                $parentId = filled($data['parent_slug'] ?? null)
                    ? Category::query()->where('slug', $data['parent_slug'])->value('id')
                    : null;
                Category::query()->where('slug', $data['slug'])->update(['parent_id' => $parentId]);
            }

            foreach ($snapshot['attributes'] ?? [] as $data) {
                $attribute = Attribute::query()->updateOrCreate(['slug' => $data['slug']], Arr::except($data, ['values']));
                $counts['attributes']++;

                foreach ($data['values'] ?? [] as $valueData) {
                    AttributeValue::query()->updateOrCreate(
                        ['attribute_id' => $attribute->id, 'slug' => $valueData['slug']],
                        $valueData,
                    );
                    $counts['attribute_values']++;
                }
            }

            foreach ($snapshot['product_tags'] ?? [] as $data) {
                ProductTag::query()->updateOrCreate(['slug' => $data['slug']], $data);
                $counts['product_tags']++;
            }

            foreach ($snapshot['users'] ?? [] as $data) {
                $passwordHash = Arr::pull($data, 'password_hash');
                $user = User::query()->where('email', $data['email'])->first();

                if (! $user && filled($data['phone'] ?? null)) {
                    $user = User::query()->where('phone', $data['phone'])->first();
                }

                $user ??= new User;
                $user->forceFill($data)->save();

                if (filled($passwordHash)) {
                    DB::table('users')->where('id', $user->id)->update(['password' => $passwordHash]);
                }
                $counts['users']++;
            }

            foreach ($snapshot['products'] ?? [] as $data) {
                $productData = Arr::except($data, ['brand_slug', 'category_slug', 'tag_slugs', 'attribute_slugs', 'variations']);
                $productData['brand_id'] = filled($data['brand_slug'] ?? null)
                    ? Brand::query()->where('slug', $data['brand_slug'])->value('id')
                    : null;
                $productData['category_id'] = filled($data['category_slug'] ?? null)
                    ? Category::query()->where('slug', $data['category_slug'])->value('id')
                    : null;

                $product = filled($data['sku'] ?? null)
                    ? Product::query()->where('sku', $data['sku'])->first()
                    : null;
                $product ??= Product::query()->where('slug', $data['slug'])->first();

                if ($product) {
                    $product->update($productData);
                } else {
                    $product = Product::query()->create($productData);
                }
                $counts['products']++;

                $tagIds = ProductTag::query()->whereIn('slug', $data['tag_slugs'] ?? [])->pluck('id');
                $attributeIds = Attribute::query()->whereIn('slug', $data['attribute_slugs'] ?? [])->pluck('id');
                $product->tags()->syncWithoutDetaching($tagIds);
                $product->attributes()->syncWithoutDetaching($attributeIds);

                foreach ($data['variations'] ?? [] as $variationData) {
                    $valueKeys = Arr::pull($variationData, 'attribute_values', []);
                    $variation = ProductVariation::query()->updateOrCreate(
                        ['product_id' => $product->id, 'sku' => $variationData['sku']],
                        $variationData,
                    );
                    $valueIds = collect($valueKeys)->map(function (string $key): ?int {
                        [$attributeSlug, $valueSlug] = array_pad(explode(':', $key, 2), 2, null);
                        $attributeId = Attribute::query()->where('slug', $attributeSlug)->value('id');

                        return $attributeId
                            ? AttributeValue::query()->where('attribute_id', $attributeId)->where('slug', $valueSlug)->value('id')
                            : null;
                    })->filter();
                    $variation->attributeValues()->syncWithoutDetaching($valueIds);
                    $counts['product_variations']++;
                }
            }

            return $counts;
        });
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path
            : base_path($path);
    }
}
