<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            // Basic information
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();

            // Brand website
            $table->string('website')->nullable();

            // Status and sorting
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // SEO
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
