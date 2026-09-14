<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slider_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('placement')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::table('banner_sliders', function (Blueprint $table): void {
            $table->foreignId('slider_group_id')
                ->nullable()
                ->after('id')
                ->constrained('slider_groups')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $now = now();
        $mainSliderId = DB::table('slider_groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Main Slider',
            'slug' => 'main-slider',
            'placement' => 'main',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('banner_sliders')
            ->whereNull('slider_group_id')
            ->update(['slider_group_id' => $mainSliderId]);

        DB::table('banner_sliders')
            ->where('slider_group_id', $mainSliderId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $slide, int $index): void {
                DB::table('banner_sliders')
                    ->where('id', $slide->id)
                    ->update(['name' => 'Slider '.($index + 1)]);
            });
    }

    public function down(): void
    {
        Schema::table('banner_sliders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('slider_group_id');
        });

        Schema::dropIfExists('slider_groups');
    }
};
