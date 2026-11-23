<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('landing_pages', 'review_images')) {
            return;
        }

        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->json('review_images')->nullable()->after('gallery_images');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('landing_pages', 'review_images')) {
            return;
        }

        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->dropColumn('review_images');
        });
    }
};
