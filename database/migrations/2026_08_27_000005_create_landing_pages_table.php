<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->string('hero_badge')->nullable();
            $table->string('headline');
            $table->text('subheadline')->nullable();
            $table->string('cta_text')->default('এখনই অর্ডার করুন');
            $table->string('hero_image')->nullable();
            $table->string('theme_color', 20)->default('#f97316');
            $table->string('offer_title')->nullable();
            $table->longText('offer_body')->nullable();
            $table->string('trust_title')->nullable();
            $table->json('benefits')->nullable();
            $table->json('gallery_images')->nullable();
            $table->json('reviews')->nullable();
            $table->json('faqs')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamp('countdown_ends_at')->nullable();
            $table->string('facebook_pixel_id', 32)->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
    }
};
