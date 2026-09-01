<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_classes')) {
            Schema::create('shipping_classes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code', 80)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_partners')) {
            Schema::create('shipping_partners', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code', 80)->unique();
                $table->string('contact_name')->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('email')->nullable();
                $table->string('tracking_url')->nullable();
                $table->string('api_provider')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_regions')) {
            Schema::create('shipping_regions', function (Blueprint $table): void {
                $table->id();
                $table->string('division', 120);
                $table->string('district', 120);
                $table->string('upazila', 120);
                $table->string('postal_code', 20)->nullable();
                $table->string('location_key')->unique();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['division', 'district', 'upazila']);
            });
        }

        if (! Schema::hasTable('shipping_region_rates')) {
            Schema::create('shipping_region_rates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shipping_region_id')->constrained()->cascadeOnDelete();
                $table->foreignId('shipping_class_id')->constrained()->cascadeOnDelete();
                $table->foreignId('shipping_partner_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('base_charge', 12, 2)->default(0);
                $table->decimal('additional_item_charge', 12, 2)->default(0);
                $table->unsignedSmallInteger('estimated_min_days')->default(1);
                $table->unsignedSmallInteger('estimated_max_days')->default(3);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->unique(['shipping_region_id', 'shipping_class_id'], 'shipping_region_class_unique');
            });
        }

        if (! Schema::hasColumn('products', 'shipping_class_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('shipping_class_id')
                    ->nullable()
                    ->after('gallery_images')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_region_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('shipping_region_id')->nullable()->after('shipping_zone')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_partner_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('shipping_partner_id')->nullable()->after('shipping_region_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_breakdown')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->json('shipping_breakdown')->nullable()->after('shipping_address');
            });
        }

        DB::table('shipping_classes')->updateOrInsert(['code' => 'standard'], [
            'name' => 'Standard',
            'description' => 'Default shipping class for existing products.',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classId = DB::table('shipping_classes')->where('code', 'standard')->value('id');

        DB::table('shipping_partners')->updateOrInsert(['code' => 'tisilo-delivery'], [
            'name' => 'Tisilo Delivery',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $partnerId = DB::table('shipping_partners')->where('code', 'tisilo-delivery')->value('id');

        $legacy = json_decode((string) DB::table('site_settings')->where('key', 'shipping')->value('values'), true) ?: [];
        $zones = collect($legacy['zones'] ?? []);
        $inside = (float) ($zones->first(fn (array $zone): bool => str_contains(strtolower((string) ($zone['name'] ?? '')), 'inside'))['amount'] ?? 80);
        $outside = (float) ($zones->first(fn (array $zone): bool => str_contains(strtolower((string) ($zone['name'] ?? '')), 'outside'))['amount'] ?? 130);

        foreach ([
            ['Dhaka', 'Dhaka', 'Dhaka Metropolitan', 'dhaka-dhaka-dhaka-metropolitan', $inside, 1, 2],
            ['Bangladesh', 'Outside Dhaka', 'Other Upazila', 'bangladesh-outside-dhaka-other-upazila', $outside, 3, 5],
        ] as [$division, $district, $upazila, $key, $charge, $minDays, $maxDays]) {
            DB::table('shipping_regions')->updateOrInsert(['location_key' => $key], [
                'division' => $division,
                'district' => $district,
                'upazila' => $upazila,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $regionId = DB::table('shipping_regions')->where('location_key', $key)->value('id');
            DB::table('shipping_region_rates')->updateOrInsert([
                'shipping_region_id' => $regionId,
                'shipping_class_id' => $classId,
            ], [
                'shipping_partner_id' => $partnerId,
                'base_charge' => $charge,
                'additional_item_charge' => 0,
                'estimated_min_days' => $minDays,
                'estimated_max_days' => $maxDays,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('products')->whereNull('shipping_class_id')->update(['shipping_class_id' => $classId]);
    }

    public function down(): void
    {
        foreach (['shipping_partner_id', 'shipping_region_id'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId($column));
            }
        }

        if (Schema::hasColumn('orders', 'shipping_breakdown')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('shipping_breakdown'));
        }

        if (Schema::hasColumn('products', 'shipping_class_id')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('shipping_class_id'));
        }
        Schema::dropIfExists('shipping_region_rates');
        Schema::dropIfExists('shipping_regions');
        Schema::dropIfExists('shipping_partners');
        Schema::dropIfExists('shipping_classes');
    }
};
