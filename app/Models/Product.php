<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'product_type',
        'brand_id',
        'category_id',
        'sku',
        'barcode',
        'purchase_price',
        'regular_price',
        'sale_price',
        'manage_stock',
        'stock_quantity',
        'low_stock_threshold',
        'stock_status',
        'short_description',
        'description',
        'featured_image',
        'gallery_images',
        'shipping_class_id',
        'weight',
        'length',
        'width',
        'height',
        'status',
        'featured',
        'sort_order',
        'seo_title',
        'meta_description',
        'meta_keywords',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',

            'manage_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',

            'gallery_images' => 'array',

            'weight' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',

            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function shippingClass(): BelongsTo
    {
        return $this->belongsTo(ShippingClass::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductTag::class,
            'product_product_tag',
            'product_id',
            'product_tag_id'
        )->withTimestamps();
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'attribute_product',
            'product_id',
            'attribute_id'
        )->withTimestamps();
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function vendorListings(): HasMany
    {
        return $this->hasMany(VendorListing::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function landingPages(): BelongsToMany
    {
        return $this->belongsToMany(LandingPage::class)
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
