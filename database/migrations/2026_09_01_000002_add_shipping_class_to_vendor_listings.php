<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table): void {
            $table->foreignId('shipping_class_id')
                ->nullable()
                ->after('product_id')
                ->constrained('shipping_classes')
                ->nullOnDelete();
        });

        DB::table('vendor_listings')
            ->select(['id', 'product_id'])
            ->orderBy('id')
            ->chunkById(200, function ($listings): void {
                foreach ($listings as $listing) {
                    DB::table('vendor_listings')
                        ->where('id', $listing->id)
                        ->update([
                            'shipping_class_id' => DB::table('products')
                                ->where('id', $listing->product_id)
                                ->value('shipping_class_id'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_class_id');
        });
    }
};
