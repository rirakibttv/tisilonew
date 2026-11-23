<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_classes', function (Blueprint $table): void {
            $table->foreignId('default_region_id')->nullable()->after('description')->constrained('shipping_regions')->nullOnDelete();
        });

        Schema::table('shipping_regions', function (Blueprint $table): void {
            $table->dropIndex(['is_inside_city']);
            $table->dropColumn('is_inside_city');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_regions', function (Blueprint $table): void {
            $table->boolean('is_inside_city')->default(false)->after('postal_code')->index();
        });

        Schema::table('shipping_classes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_region_id');
        });
    }
};
