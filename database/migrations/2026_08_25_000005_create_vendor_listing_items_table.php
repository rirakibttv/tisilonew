<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_listing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variation_id')
                ->nullable()
                ->constrained('product_variations')
                ->restrictOnDelete();
            $table->string('variation_key', 80)->default('base');
            $table->string('seller_sku');
            $table->string('barcode')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('regular_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('backorders_allowed')->default(false);
            $table->string('status', 30)->default('active')->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vendor_listing_id', 'variation_key']);
            $table->unique(['vendor_id', 'seller_sku']);
            $table->index(['vendor_listing_id', 'status']);
            $table->index('product_variation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_listing_items');
    }
};
