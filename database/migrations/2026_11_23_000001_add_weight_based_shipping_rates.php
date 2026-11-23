<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_partners', function (Blueprint $table): void {
            $table->decimal('inside_city_first_kg_charge', 12, 2)->default(75)->after('estimated_max_days');
            $table->decimal('outside_city_first_kg_charge', 12, 2)->default(135)->after('inside_city_first_kg_charge');
            $table->decimal('additional_kg_charge', 12, 2)->default(20)->after('outside_city_first_kg_charge');
        });

        Schema::table('shipping_regions', function (Blueprint $table): void {
            $table->boolean('is_inside_city')->default(false)->after('postal_code')->index();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('weight_unit', 2)->default('kg')->after('weight');
        });

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->string('weight_unit', 2)->default('kg')->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', fn (Blueprint $table) => $table->dropColumn('weight_unit'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('weight_unit'));
        Schema::table('shipping_regions', function (Blueprint $table): void {
            $table->dropIndex(['is_inside_city']);
            $table->dropColumn('is_inside_city');
        });
        Schema::table('shipping_partners', function (Blueprint $table): void {
            $table->dropColumn(['inside_city_first_kg_charge', 'outside_city_first_kg_charge', 'additional_kg_charge']);
        });
    }
};
