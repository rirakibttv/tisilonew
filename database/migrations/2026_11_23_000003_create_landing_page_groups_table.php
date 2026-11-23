<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->foreignId('group_id')->nullable()->after('id')->constrained('landing_page_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_id');
        });

        Schema::dropIfExists('landing_page_groups');
    }
};
