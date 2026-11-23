<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shippingClassId = DB::table('shipping_classes')
            ->where('code', 'dhaka-seller-2 kg')
            ->where('is_active', true)
            ->value('id');

        if (! $shippingClassId) {
            return;
        }

        DB::table('products')
            ->where('slug', 'premium-quality-twill-bed-sheet-set-king-size-pt-053')
            ->whereNull('shipping_class_id')
            ->update([
                'shipping_class_id' => $shippingClassId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Keep the product's shipping class if this migration is rolled back.
    }
};
