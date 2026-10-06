<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BannerSlider;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\InventoryStock;
use App\Models\LandingPage;
use App\Models\Permission;
use App\Models\PopupOffer;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\ProductVariation;
use App\Models\Role;
use App\Models\ShippingClass;
use App\Models\ShippingPartner;
use App\Models\ShippingRegion;
use App\Models\ShippingRegionRate;
use App\Models\SiteSetting;
use App\Models\SliderGroup;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
            'categories' => Category::query()->with('parent.parent')->orderBy('slug')->get()
                ->sortBy(fn (Category $category): int => substr_count($category->hierarchicalPath(), '/'))
                ->values()
                ->map(fn (Category $category): array => [
                    ...Arr::only(
                        $category->toArray(),
                        ['name', 'slug', 'image', 'description', 'status', 'show_on_homepage', 'sort_order', 'seo_title', 'meta_description'],
                    ),
                    'path' => $category->hierarchicalPath(),
                    'parent_path' => $category->parent?->hierarchicalPath(),
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
                'values' => $this->exportableSettingValues($setting),
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
                    ['name', 'code', 'contact_name', 'phone', 'email', 'tracking_url', 'api_provider', 'estimated_min_days', 'estimated_max_days', 'notes', 'is_active'],
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
                            'base_charge', 'additional_item_charge', 'is_active',
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
                    'category:id,parent_id,slug',
                    'category.parent.parent',
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
                    'category_path' => $product->category?->hierarchicalPath(),
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
            'wishlists' => DB::table('wishlists')
                ->join('users', 'users.id', '=', 'wishlists.user_id')
                ->join('products', 'products.id', '=', 'wishlists.product_id')
                ->orderBy('users.email')
                ->orderBy('products.slug')
                ->get(['users.email as user_email', 'products.slug as product_slug'])
                ->map(fn ($item): array => (array) $item)
                ->all(),
            'landing_pages' => LandingPage::query()
                ->with('products:id,slug')
                ->orderBy('slug')
                ->get()
                ->map(fn (LandingPage $landingPage): array => [
                    ...Arr::only($landingPage->toArray(), [
                        'name', 'header_title', 'slug', 'status', 'hero_badge', 'headline', 'subheadline', 'cta_text',
                        'hero_image', 'theme_color', 'offer_title', 'offer_body', 'trust_title',
                        'benefits', 'gallery_images', 'reviews', 'faqs', 'video_url',
                        'countdown_ends_at', 'meta_title', 'meta_description',
                        'og_image', 'published_at',
                    ]),
                    'product_slugs' => $landingPage->products->pluck('slug')->values()->all(),
                ])->all(),
            'slider_groups' => SliderGroup::query()
                ->orderBy('sort_order')
                ->orderBy('uuid')
                ->get()
                ->map(fn (SliderGroup $group): array => Arr::only($group->toArray(), [
                    'uuid', 'name', 'slug', 'placement', 'is_active', 'sort_order',
                ]))
                ->all(),
            'banner_sliders' => BannerSlider::query()
                ->with('group')
                ->orderBy('sort_order')
                ->orderBy('uuid')
                ->get()
                ->map(fn (BannerSlider $slider): array => [
                    ...Arr::only($slider->toArray(), [
                        'uuid', 'name', 'image', 'destination_url', 'is_active', 'sort_order',
                    ]),
                    'slider_group_slug' => $slider->group?->slug ?? 'main-slider',
                ])
                ->all(),
            'popup_offers' => PopupOffer::query()
                ->orderBy('uuid')
                ->get()
                ->map(fn (PopupOffer $popupOffer): array => Arr::only($popupOffer->toArray(), [
                    'uuid', 'title', 'description', 'image', 'link_url', 'button_text', 'footer_text',
                    'is_active', 'display_delay_seconds', 'starts_at', 'ends_at',
                ]))
                ->all(),
            'flash_sales' => FlashSale::query()
                ->with(['items.product:id,slug'])
                ->orderBy('starts_at')
                ->orderBy('uuid')
                ->get()
                ->map(fn (FlashSale $flashSale): array => [
                    ...Arr::only($flashSale->toArray(), [
                        'uuid', 'name', 'starts_at', 'ends_at', 'is_active',
                    ]),
                    'items' => $flashSale->items
                        ->sortBy('sort_order')
                        ->map(fn ($item): array => [
                            'product_slug' => $item->product?->slug,
                            ...Arr::only($item->toArray(), [
                                'flash_price', 'sort_order', 'is_active',
                            ]),
                        ])
                        ->filter(fn (array $item): bool => filled($item['product_slug']))
                        ->values()
                        ->all(),
                ])
                ->all(),
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
            $counts = ['brands' => 0, 'categories' => 0, 'attributes' => 0, 'attribute_values' => 0, 'product_tags' => 0, 'permissions' => 0, 'roles' => 0, 'users' => 0, 'site_settings' => 0, 'shipping_classes' => 0, 'shipping_partners' => 0, 'shipping_regions' => 0, 'shipping_region_rates' => 0, 'products' => 0, 'product_variations' => 0, 'wishlists' => 0, 'landing_pages' => 0, 'slider_groups' => 0, 'banner_sliders' => 0, 'popup_offers' => 0, 'flash_sales' => 0, 'flash_sale_items' => 0, 'vendors' => 0, 'vendor_members' => 0, 'vendor_warehouses' => 0, 'vendor_listings' => 0, 'vendor_listing_items' => 0, 'inventory_stocks' => 0];

            foreach ($snapshot['brands'] ?? [] as $data) {
                Brand::query()->updateOrCreate(['slug' => $data['slug']], $data);
                $counts['brands']++;
            }

            $categoryRows = collect($snapshot['categories'] ?? [])
                ->sortBy(fn (array $data): int => filled($data['path'] ?? null)
                    ? substr_count((string) $data['path'], '/')
                    : (filled($data['parent_slug'] ?? null) ? 1 : 0));
            $importedCategoryIds = [];

            foreach ($categoryRows as $data) {
                $path = Arr::pull($data, 'path');
                $parentPath = Arr::pull($data, 'parent_path');
                $parentSlug = Arr::pull($data, 'parent_slug');
                $parentId = null;

                if (filled($parentPath)) {
                    $parentId = $importedCategoryIds[$parentPath]
                        ?? $this->findCategoryByPath((string) $parentPath)?->getKey();
                } elseif (filled($parentSlug)) {
                    $parentId = Category::query()->where('slug', $parentSlug)->value('id');
                }

                $category = Category::query()->updateOrCreate(
                    ['parent_id' => $parentId, 'slug' => $data['slug']],
                    $data,
                );
                $importedCategoryIds[$path ?: $category->hierarchicalPath()] = $category->getKey();
                $counts['categories']++;
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
                $setting->values = $this->importableSettingValues(
                    (string) $data['key'],
                    $data['values'] ?? [],
                    $setting->values ?? [],
                );
                $setting->save();
                SiteSetting::forget($data['key']);
                $counts['site_settings']++;
            }

            foreach ($snapshot['shipping_classes'] ?? [] as $data) {
                ShippingClass::query()->updateOrCreate(['code' => $data['code']], $data);
                $counts['shipping_classes']++;
            }

            $legacyPartnerEstimates = collect($snapshot['shipping_regions'] ?? [])
                ->flatMap(fn (array $region): array => collect($region['rates'] ?? [])
                    ->filter(fn (array $rate): bool => filled($rate['shipping_partner_code'] ?? null))
                    ->map(fn (array $rate): array => [
                        'code' => $rate['shipping_partner_code'],
                        'min_days' => $rate['estimated_min_days'] ?? null,
                        'max_days' => $rate['estimated_max_days'] ?? null,
                    ])
                    ->all())
                ->groupBy('code')
                ->map(fn (Collection $rates): array => [
                    'estimated_min_days' => max(1, (int) ($rates->min('min_days') ?: 1)),
                    'estimated_max_days' => max(1, (int) ($rates->max('max_days') ?: 3)),
                ]);

            foreach ($snapshot['shipping_partners'] ?? [] as $data) {
                if (! array_key_exists('estimated_min_days', $data) || ! array_key_exists('estimated_max_days', $data)) {
                    $data = [...($legacyPartnerEstimates->get($data['code']) ?? []), ...$data];
                }

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
                    Arr::forget($rateData, ['estimated_min_days', 'estimated_max_days']);
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
                $productData = Arr::except($data, ['brand_slug', 'category_slug', 'category_path', 'shipping_class_code', 'tag_slugs', 'attribute_slugs', 'variations']);
                $productData['brand_id'] = filled($data['brand_slug'] ?? null)
                    ? Brand::query()->where('slug', $data['brand_slug'])->value('id')
                    : null;
                $productData['category_id'] = filled($data['category_path'] ?? null)
                    ? $this->findCategoryByPath((string) $data['category_path'])?->getKey()
                    : (filled($data['category_slug'] ?? null)
                        ? Category::query()->where('slug', $data['category_slug'])->value('id')
                        : null);
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

            foreach ($snapshot['wishlists'] ?? [] as $data) {
                $userId = User::query()->where('email', $data['user_email'])->value('id');
                $productId = Product::query()->where('slug', $data['product_slug'])->value('id');
                if (! $userId || ! $productId) {
                    continue;
                }

                DB::table('wishlists')->updateOrInsert(
                    ['user_id' => $userId, 'product_id' => $productId],
                    ['updated_at' => now(), 'created_at' => now()],
                );
                $counts['wishlists']++;
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

            foreach ($snapshot['slider_groups'] ?? [] as $data) {
                SliderGroup::query()->updateOrCreate(['placement' => $data['placement']], $data);
                $counts['slider_groups']++;
            }

            $mainSliderGroup = SliderGroup::main();

            foreach ($snapshot['banner_sliders'] ?? [] as $data) {
                $groupSlug = Arr::pull($data, 'slider_group_slug', 'main-slider');
                $groupId = SliderGroup::query()->where('slug', $groupSlug)->value('id')
                    ?? $mainSliderGroup->getKey();

                BannerSlider::query()->updateOrCreate(
                    ['uuid' => $data['uuid']],
                    [...$data, 'slider_group_id' => $groupId],
                );
                $counts['banner_sliders']++;
            }

            foreach ($snapshot['popup_offers'] ?? [] as $data) {
                PopupOffer::query()->updateOrCreate(['uuid' => $data['uuid']], $data);
                $counts['popup_offers']++;
            }

            foreach ($snapshot['flash_sales'] ?? [] as $data) {
                $items = Arr::pull($data, 'items', []);
                $flashSale = FlashSale::query()->updateOrCreate(['uuid' => $data['uuid']], $data);
                $counts['flash_sales']++;

                foreach ($items as $itemData) {
                    $productSlug = Arr::pull($itemData, 'product_slug');
                    $productId = Product::query()->where('slug', $productSlug)->value('id');
                    if (! $productId) {
                        continue;
                    }

                    $flashSale->items()->updateOrCreate(
                        ['product_id' => $productId],
                        $itemData,
                    );
                    $counts['flash_sale_items']++;
                }
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

    private function findCategoryByPath(string $path): ?Category
    {
        $category = null;

        foreach (array_values(array_filter(explode('/', trim($path, '/')))) as $index => $slug) {
            $category = Category::query()
                ->where('slug', $slug)
                ->when(
                    $index === 0,
                    fn ($query) => $query->whereNull('parent_id'),
                    fn ($query) => $query->where('parent_id', $category?->getKey()),
                )
                ->first();

            if (! $category) {
                return null;
            }
        }

        return $category;
    }

    /** @return array<string, mixed> */
    private function exportableSettingValues(SiteSetting $setting): array
    {
        $values = $setting->values ?? [];

        // Meta credentials and activation state belong to each deployment. Only the
        // CAPI event policy is portable; the Commerce Catalog connection is entirely
        // production-owned so a catalog import cannot replace its IDs or sync state.
        return match ($setting->key) {
            'facebook_capi' => Arr::only($values, ['events']),
            'facebook_catalog' => [],
            default => $values,
        };
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function importableSettingValues(string $key, array $incoming, array $existing): array
    {
        return match ($key) {
            'facebook_capi' => [
                ...$incoming,
                ...Arr::only($existing, ['enabled', 'pixel_id', 'api_version', 'test_event_code']),
            ],
            'facebook_catalog' => $existing,
            default => $incoming,
        };
    }
}
