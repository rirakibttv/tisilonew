<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Variation identity
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();

            // Pricing
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('regular_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();

            // Inventory
            $table->integer('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);

            $table->string('stock_status')->default('in_stock');
            // in_stock / out_of_stock / on_backorder

            // Variation image
            $table->string('image')->nullable();

            // Shipping / physical data
            $table->decimal('weight', 10, 2)->nullable();

            // State
            $table->boolean('status')->default(true);
            $table->boolean('is_default')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('stock_status');
            $table->index('status');
            $table->index('is_default');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
