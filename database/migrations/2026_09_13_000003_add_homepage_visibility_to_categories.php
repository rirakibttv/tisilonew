<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('show_on_homepage')
                ->default(false)
                ->after('status')
                ->index('categories_homepage_visibility_index');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_homepage_visibility_index');
            $table->dropColumn('show_on_homepage');
        });
    }
};
