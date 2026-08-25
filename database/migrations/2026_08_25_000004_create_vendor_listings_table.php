<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->string('condition', 30)->default('new');
            $table->string('fulfillment_type', 30)->default('vendor');
            $table->string('warranty')->nullable();
            $table->unsignedInteger('min_order_quantity')->default(1);
            $table->unsignedInteger('max_order_quantity')->nullable();
            $table->unsignedInteger('handling_time_days')->default(1);
            $table->decimal('commission_rate_override', 5, 2)->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vendor_id', 'product_id']);
            $table->index(['product_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_listings');
    }
};
