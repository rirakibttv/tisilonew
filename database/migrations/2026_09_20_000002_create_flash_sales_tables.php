<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('flash_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('flash_price', 12, 2);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['flash_sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
    }
};
