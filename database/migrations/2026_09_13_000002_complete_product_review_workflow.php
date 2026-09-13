<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->foreignId('created_by')
                ->nullable()
                ->after('order_item_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('source', 20)->default('customer')->after('created_by')->index();
            $table->unique(['product_id', 'user_id'], 'product_reviews_product_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropUnique('product_reviews_product_user_unique');
            $table->dropForeign(['created_by']);
            $table->dropIndex(['source']);
            $table->dropColumn(['created_by', 'source']);
        });
    }
};
