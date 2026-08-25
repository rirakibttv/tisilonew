<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->string('slug')->unique();

            $table->string('product_type')->default('simple');
            // simple / variable

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            // Product Codes
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();

            // Pricing
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('regular_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->nullable();

            // Inventory
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);

            $table->string('stock_status')->default('in_stock');
            // in_stock / out_of_stock / on_backorder

            // Content
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // Images
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable();

            // Shipping
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();

            // Product Status
            $table->string('status')->default('draft');
            // draft / pending / published

            $table->boolean('featured')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            // SEO
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('name');
            $table->index('product_type');
            $table->index('status');
            $table->index('featured');
            $table->index('stock_status');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
