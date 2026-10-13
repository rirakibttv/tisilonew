<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_value', 14, 2);
            $table->decimal('minimum_order_amount', 14, 2)->default(0);
            $table->decimal('maximum_discount_amount', 14, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('landing_page_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('coupon_code', 64)->nullable()->after('coupon_id')->index();
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('customer_key', 64)->nullable();
            $table->decimal('discount_amount', 14, 2);
            $table->timestamp('used_at')->index();
            $table->timestamps();

            $table->index(['coupon_id', 'customer_key']);
            $table->index(['coupon_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropIndex(['coupon_code']);
            $table->dropColumn('coupon_code');
        });

        Schema::dropIfExists('coupons');
    }
};
