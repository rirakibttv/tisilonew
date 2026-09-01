<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\LandingPage;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\ProductVariation;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\ShippingClass;
use App\Models\ShippingPartner;
use App\Models\ShippingRegion;
use App\Models\ShippingRegionRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            'permissions' => Permission::query()->orderBy('slug')->get()->map(fn (Permission $permission): array => Arr::only(
                $permission->toArray(),
                ['name', 'slug', 'group', 'description', 'status'],
            ))->all(),
            'roles' => Role::query()->with('permissions:id,slug')->orderBy('slug')->get()->map(fn (Role $role): array => [
                ...Arr::only($role->toArray(), ['name', 'slug', 'description', 'is_system', 'status']),
                'permission_slugs' => $role->permissions->pluck('slug')->values()->all(),
            ])->all(),
            'users' => User::query()->with('accessRole:id,slug')->orderBy('email')->get()->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'access_role_slug' => $user->accessRole?->slug,
                'status' => $user->status->value,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
            ])->all(),
            'site_settings' => SiteSetting::query()->orderBy('key')->get()->map(fn (SiteSetting $setting): array => [
                'key' => $setting->key,
                'values' => $setting->values ?? [],
            ])->all(),
            'shipping_classes' => ShippingClass::query()->orderBy('code')->get()->map(
                fn (ShippingClass $class): array => Arr::only(
                    $class->toArray(),
                    ['name', 'code', 'description', 'is_active', 'sort_order'],
                ),
            )->all(),
            'shipping_partners' => ShippingPartner::query()->orderBy('code')->get()->map(
                fn (ShippingPartner $partner): array => Arr::only(
                    $partner->toArray(),
                    ['name', 'code', 'contact_name', 'phone', 'email', 'tracking_url', 'api_provider', 'notes', 'is_active'],
                ),
            )->all(),
            'shipping_regions' => ShippingRegion::query()
                ->with(['rates.shippingClass:id,code', 'rates.partner:id,code'])
                ->orderBy('location_key')
                ->get()
                ->map(fn (ShippingRegion $region): array => [
                    ...Arr::only($region->toArray(), [
                        'division', 'district', 'upazila', 'postal_code', 'location_key', 'is_active', 'sort_order',
                    ]),
                    'rates' => $region->rates->map(fn (ShippingRegionRate $rate): array => [
                        ...Arr::only($rate->toArray(), [
                            'base_charge', 'additional_item_charge', 'estimated_min_days',
                            'estimated_max_days', 'is_active',
                        ]),
                        'shipping_class_code' => $rate->shippingClass->code,
                        'shipping_partner_code' => $rate->partner?->code,
                    ])->all(),
                ])->all(),
            'vendors' => Vendor::query()
                ->with([
                    'owner:id,email',
                    'approver:id,email',
                    'members:id,email',
                    'warehouses',
                    'listings.product:id,slug',
                    'listings.shippingClass:id,code',
                    'listings.approver:id,email',
                    'listings.items.productVariation:id,sku',
                    'listings.items.stocks.warehouse:id,code',
                ])
                ->orderBy('slug')
                ->get()
                ->map(fn (Vendor $vendor): array => [
                    ...Arr::only($vendor->toArray(), [
                        'name', 'slug', 'legal_name', 'email', 'phone', 'website', 'logo', 'banner',
                        'description', 'status', 'commission_rate', 'approved_at',
                    ]),
                    'owner_email' => $vendor->owner->email,
                    'approved_by_email' => $vendor->approver?->email,
                    'members' => $vendor->members->map(fn (User $member): array => [
                        'email' => $member->email,
                        'role' => $member->pivot->role,
                        'status' => $member->pivot->status,
                        'permissions' => is_array($member->pivot->permissions)
                            ? $member->pivot->permissions
                            : json_decode($member->pivot->permissions ?? '[]', true),
                        'joined_at' => $member->pivot->joined_at,
                    ])->all(),
                    'warehouses' => $vendor->warehouses->map(fn (VendorWarehouse $warehouse): array => Arr::only(
                        $warehouse->toArray(),
                        [
                            'name', 'code', 'contact_name', 'phone', 'address_line_1', 'address_line_2',
                            'district', 'upazila', 'postal_code', 'is_default', 'status',
                        ],
                    ))->all(),
                    'listings' => $vendor->listings->map(fn (VendorListing $listing): array => [
                        ...Arr::only($listing->toArray(), [
                            'status', 'condition', 'fulfillment_type', 'warranty', 'min_order_quantity',
                            'max_order_quantity', 'handling_time_days', 'commission_rate_override',
                            'is_featured', 'rejection_reason', 'approved_at', 'published_at',
                        ]),
                        'product_slug' => $listing->product->slug,
                        'shipping_class_code' => $listing->shippingClass?->code,
                        'approved_by_email' => $listing->approver?->email,
                        'items' => $listing->items->map(fn (VendorListingItem $item): array => [
                            ...Arr::only($item->toArray(), [
                                'seller_sku', 'barcode', 'purchase_price', 'regular_price', 'sale_price',
                                'low_stock_threshold', 'backorders_allowed', 'status', 'is_default',
                            ]),
                            'product_variation_sku' => $item->productVariation?->sku,
                            'stocks' => $item->stocks->map(fn (InventoryStock $stock): array => [
                                'warehouse_code' => $stock->warehouse->code,
                                ...Arr::only($stock->toArray(), [
                                    'quantity', 'reserved_quantity', 'incoming_quantity', 'reorder_point',
                                ]),
                            ])->all(),
                        ])->all(),
                    ])->all(),
                ])->all(),
            'products' => Product::query()
                ->with([
                    'brand:id,slug',
                    'category:id,slug',
                    'shippingClass:id,code',
                    'tags:id,slug',
                    'attributes:id,slug',
                    'variations' => fn ($query) => $query->orderByRaw('sku is null')->orderBy('sku')->orderBy('id'),
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
                    'shipping_class_code' => $product->shippingClass?->code,
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
            'landing_pages' => LandingPage::query()
                ->with('products:id,slug')
                ->orderBy('slug')
                ->get()
                ->map(fn (LandingPage $landingPage): array => [
                    ...Arr::only($landingPage->toArray(), [
                        'name', 'slug', 'status', 'hero_badge', 'headline', 'subheadline', 'cta_text',
                        'hero_image', 'theme_color', 'offer_title', 'offer_body', 'trust_title',
                        'benefits', 'gallery_images', 'reviews', 'faqs', 'video_url',
                        'countdown_ends_at', 'facebook_pixel_id', 'meta_title', 'meta_description',
                        'og_image', 'published_at',
                    ]),
                    'product_slugs' => $landingPage->products->pluck('slug')->values()->all(),
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
            $counts = ['brands' => 0, 'categories' => 0, 'attributes' => 0, 'attribute_values' => 0, 'product_tags' => 0, 'permissions' => 0, 'roles' => 0, 'users' => 0, 'site_settings' => 0, 'shipping_classes' => 0, 'shipping_partners' => 0, 'shipping_regions' => 0, 'shipping_region_rates' => 0, 'products' => 0, 'product_variations' => 0, 'landing_pages' => 0, 'vendors' => 0, 'vendor_members' => 0, 'vendor_warehouses' => 0, 'vendor_listings' => 0, 'vendor_listing_items' => 0, 'inventory_stocks' => 0];

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

            foreach ($snapshot['permissions'] ?? [] as $data) {
                Permission::query()->updateOrCreate(['slug' => $data['slug']], $data);
                $counts['permissions']++;
            }

            foreach ($snapshot['roles'] ?? [] as $data) {
                $permissionSlugs = Arr::pull($data, 'permission_slugs', []);
                $role = Role::query()->updateOrCreate(['slug' => $data['slug']], $data);
                $permissionIds = Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id');
                $role->permissions()->sync($permissionIds);
                $counts['roles']++;
            }

            foreach ($snapshot['users'] ?? [] as $data) {
                $hasAccessRole = array_key_exists('access_role_slug', $data);
                $accessRoleSlug = Arr::pull($data, 'access_role_slug');
                if ($hasAccessRole) {
                    $data['rbac_role_id'] = filled($accessRoleSlug)
                        ? Role::query()->where('slug', $accessRoleSlug)->value('id')
                        : null;
                }
                $user = User::query()->where('email', $data['email'])->first();

                if ($user) {
                    $user->forceFill($data)->save();
                } else {
                    User::query()->create([
                        ...$data,
                        'password' => Hash::make(Str::random(64)),
                    ]);
                }
                $counts['users']++;
            }

            foreach ($snapshot['site_settings'] ?? [] as $data) {
                $setting = SiteSetting::query()->firstOrNew(['key' => $data['key']]);
                $setting->values = $data['values'] ?? [];
                $setting->save();
                SiteSetting::forget($data['key']);
                $counts['site_settings']++;
            }

            foreach ($snapshot['shipping_classes'] ?? [] as $data) {
                ShippingClass::query()->updateOrCreate(['code' => $data['code']], $data);
                $counts['shipping_classes']++;
            }

            foreach ($snapshot['shipping_partners'] ?? [] as $data) {
                ShippingPartner::query()->updateOrCreate(['code' => $data['code']], $data);
                $counts['shipping_partners']++;
            }

            foreach ($snapshot['shipping_regions'] ?? [] as $data) {
                $rates = Arr::pull($data, 'rates', []);
                $region = ShippingRegion::query()->updateOrCreate(['location_key' => $data['location_key']], $data);
                $counts['shipping_regions']++;

                foreach ($rates as $rateData) {
                    $classCode = Arr::pull($rateData, 'shipping_class_code');
                    $partnerCode = Arr::pull($rateData, 'shipping_partner_code');
                    $classId = ShippingClass::query()->where('code', $classCode)->value('id');
                    if (! $classId) {
                        continue;
                    }
                    $rateData['shipping_partner_id'] = filled($partnerCode)
                        ? ShippingPartner::query()->where('code', $partnerCode)->value('id')
                        : null;
                    ShippingRegionRate::query()->updateOrCreate(
                        ['shipping_region_id' => $region->id, 'shipping_class_id' => $classId],
                        $rateData,
                    );
                    $counts['shipping_region_rates']++;
                }
            }

            foreach ($snapshot['products'] ?? [] as $data) {
                $productData = Arr::except($data, ['brand_slug', 'category_slug', 'shipping_class_code', 'tag_slugs', 'attribute_slugs', 'variations']);
                $productData['brand_id'] = filled($data['brand_slug'] ?? null)
                    ? Brand::query()->where('slug', $data['brand_slug'])->value('id')
                    : null;
                $productData['category_id'] = filled($data['category_slug'] ?? null)
                    ? Category::query()->where('slug', $data['category_slug'])->value('id')
                    : null;
                if (array_key_exists('shipping_class_code', $data)) {
                    $productData['shipping_class_id'] = filled($data['shipping_class_code'])
                        ? ShippingClass::query()->where('code', $data['shipping_class_code'])->value('id')
                        : null;
                }

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

                $unclaimedSkuLessVariations = $product->variations()
                    ->whereNull('sku')
                    ->with('attributeValues:id')
                    ->orderBy('id')
                    ->get();

                foreach ($data['variations'] ?? [] as $variationData) {
                    $valueKeys = Arr::pull($variationData, 'attribute_values', []);
                    $valueIds = collect($valueKeys)->map(function (string $key): ?int {
                        [$attributeSlug, $valueSlug] = array_pad(explode(':', $key, 2), 2, null);
                        $attributeId = Attribute::query()->where('slug', $attributeSlug)->value('id');

                        return $attributeId
                            ? AttributeValue::query()->where('attribute_id', $attributeId)->where('slug', $valueSlug)->value('id')
                            : null;
                    })->filter()->map(fn ($id): int => (int) $id)->sort()->values();

                    if (filled($variationData['sku'] ?? null)) {
                        $variation = ProductVariation::query()
                            ->where('sku', $variationData['sku'])
                            ->first();

                        if ($variation) {
                            $variation->update($variationData);
                        } else {
                            $variation = ProductVariation::query()->create([
                                'product_id' => $product->id,
                                ...$variationData,
                            ]);
                        }
                    } else {
                        $variation = $unclaimedSkuLessVariations->first(
                            fn (ProductVariation $candidate): bool => $candidate->attributeValues
                                ->pluck('id')
                                ->map(fn ($id): int => (int) $id)
                                ->sort()
                                ->values()
                                ->all() === $valueIds->all(),
                        ) ?? $unclaimedSkuLessVariations->first();

                        if ($variation) {
                            $variation->update($variationData);
                            $unclaimedSkuLessVariations = $unclaimedSkuLessVariations
                                ->reject(fn (ProductVariation $candidate): bool => $candidate->is($variation));
                        } else {
                            $variation = ProductVariation::query()->create([
                                'product_id' => $product->id,
                                ...$variationData,
                            ]);
                        }
                    }

                    $variation->attributeValues()->sync($valueIds);
                    $counts['product_variations']++;
                }
            }

            foreach ($snapshot['landing_pages'] ?? [] as $data) {
                $productSlugs = Arr::pull($data, 'product_slugs', []);
                $landingPage = LandingPage::query()->updateOrCreate(
                    ['slug' => $data['slug']],
                    $data,
                );
                $productIds = Product::query()->whereIn('slug', $productSlugs)->pluck('id');
                $landingPage->products()->sync($productIds);
                $counts['landing_pages']++;
            }

            foreach ($snapshot['vendors'] ?? [] as $data) {
                $ownerId = User::query()->where('email', $data['owner_email'])->value('id');
                if (! $ownerId) {
                    continue;
                }

                $vendorData = Arr::except($data, [
                    'owner_email', 'approved_by_email', 'members', 'warehouses', 'listings',
                ]);
                $vendorData['owner_id'] = $ownerId;
                $vendorData['approved_by'] = filled($data['approved_by_email'] ?? null)
                    ? User::query()->where('email', $data['approved_by_email'])->value('id')
                    : null;
                $vendor = Vendor::query()->updateOrCreate(['slug' => $data['slug']], $vendorData);
                $counts['vendors']++;

                foreach ($data['members'] ?? [] as $memberData) {
                    $memberId = User::query()->where('email', $memberData['email'])->value('id');
                    if (! $memberId) {
                        continue;
                    }
                    $vendor->members()->syncWithoutDetaching([
                        $memberId => Arr::only($memberData, ['role', 'status', 'permissions', 'joined_at']),
                    ]);
                    $counts['vendor_members']++;
                }

                foreach ($data['warehouses'] ?? [] as $warehouseData) {
                    VendorWarehouse::query()->updateOrCreate(
                        ['vendor_id' => $vendor->id, 'code' => $warehouseData['code']],
                        $warehouseData,
                    );
                    $counts['vendor_warehouses']++;
                }

                foreach ($data['listings'] ?? [] as $listingData) {
                    $productId = Product::query()->where('slug', $listingData['product_slug'])->value('id');
                    if (! $productId) {
                        continue;
                    }

                    $items = Arr::pull($listingData, 'items', []);
                    $approvedByEmail = Arr::pull($listingData, 'approved_by_email');
                    $shippingClassCodeExists = array_key_exists('shipping_class_code', $listingData);
                    $shippingClassCode = Arr::pull($listingData, 'shipping_class_code');
                    Arr::forget($listingData, 'product_slug');
                    $listingData['approved_by'] = filled($approvedByEmail)
                        ? User::query()->where('email', $approvedByEmail)->value('id')
                        : null;
                    if ($shippingClassCodeExists) {
                        $listingData['shipping_class_id'] = filled($shippingClassCode)
                            ? ShippingClass::query()->where('code', $shippingClassCode)->value('id')
                            : null;
                    }
                    $listing = VendorListing::query()->updateOrCreate(
                        ['vendor_id' => $vendor->id, 'product_id' => $productId],
                        $listingData,
                    );
                    $counts['vendor_listings']++;

                    foreach ($items as $itemData) {
                        $stocks = Arr::pull($itemData, 'stocks', []);
                        $variationSku = Arr::pull($itemData, 'product_variation_sku');
                        $itemData['product_variation_id'] = filled($variationSku)
                            ? ProductVariation::query()->where('product_id', $productId)->where('sku', $variationSku)->value('id')
                            : null;
                        $item = VendorListingItem::query()->updateOrCreate(
                            ['vendor_id' => $vendor->id, 'seller_sku' => $itemData['seller_sku']],
                            ['vendor_listing_id' => $listing->id, ...$itemData],
                        );
                        $counts['vendor_listing_items']++;

                        foreach ($stocks as $stockData) {
                            $warehouseCode = Arr::pull($stockData, 'warehouse_code');
                            $warehouseId = VendorWarehouse::query()
                                ->where('vendor_id', $vendor->id)
                                ->where('code', $warehouseCode)
                                ->value('id');
                            if (! $warehouseId) {
                                continue;
                            }
                            InventoryStock::query()->updateOrCreate(
                                [
                                    'vendor_listing_item_id' => $item->id,
                                    'vendor_warehouse_id' => $warehouseId,
                                ],
                                $stockData,
                            );
                            $counts['inventory_stocks']++;
                        }
                    }
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
