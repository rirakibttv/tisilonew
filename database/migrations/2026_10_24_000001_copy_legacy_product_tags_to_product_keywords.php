<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasColumn('products', 'meta_keywords')
            || ! Schema::hasTable('product_product_tag')
            || ! Schema::hasTable('product_tags')) {
            return;
        }

        Schema::table('products', function ($table): void {
            $table->text('meta_keywords')->nullable()->change();
        });

        DB::table('products')
            ->select('id')
            ->where(fn ($query) => $query->whereNull('meta_keywords')->orWhere('meta_keywords', ''))
            ->orderBy('id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $keywords = DB::table('product_product_tag')
                        ->join('product_tags', 'product_tags.id', '=', 'product_product_tag.product_tag_id')
                        ->where('product_product_tag.product_id', $product->id)
                        ->orderBy('product_tags.sort_order')
                        ->orderBy('product_tags.id')
                        ->pluck('product_tags.name')
                        ->filter()
                        ->unique()
                        ->take(10)
                        ->implode(', ');

                    if ($keywords !== '') {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->where(fn ($query) => $query->whereNull('meta_keywords')->orWhere('meta_keywords', ''))
                            ->update(['meta_keywords' => $keywords]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Keep copied keywords: later edits may have made them product-specific.
    }
};
