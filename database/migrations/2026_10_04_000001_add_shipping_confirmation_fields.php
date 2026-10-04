<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'confirmed_by')) {
                $table->foreignId('confirmed_by')
                    ->nullable()
                    ->after('confirmed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'shipping_status')) {
                $table->string('shipping_status', 80)
                    ->nullable()
                    ->after('tracking_number')
                    ->index();
            }

            if (! Schema::hasColumn('orders', 'shipping_status_synced_at')) {
                $table->timestamp('shipping_status_synced_at')
                    ->nullable()
                    ->after('shipping_status');
            }
        });

        Schema::table('shipping_regions', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipping_regions', 'pathao_city_id')) {
                $table->unsignedBigInteger('pathao_city_id')->nullable()->after('postal_code');
            }

            if (! Schema::hasColumn('shipping_regions', 'pathao_zone_id')) {
                $table->unsignedBigInteger('pathao_zone_id')->nullable()->after('pathao_city_id');
            }

            if (! Schema::hasColumn('shipping_regions', 'pathao_area_id')) {
                $table->unsignedBigInteger('pathao_area_id')->nullable()->after('pathao_zone_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'confirmed_by')) {
                $table->dropConstrainedForeignId('confirmed_by');
            }

            $columns = collect(['shipping_status', 'shipping_status_synced_at'])
                ->filter(fn (string $column): bool => Schema::hasColumn('orders', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('shipping_regions', function (Blueprint $table): void {
            $columns = collect(['pathao_city_id', 'pathao_zone_id', 'pathao_area_id'])
                ->filter(fn (string $column): bool => Schema::hasColumn('shipping_regions', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
