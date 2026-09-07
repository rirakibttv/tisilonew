<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('landing_pages', 'header_title')) {
        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->string('header_title')->nullable()->after('name');
        });

        DB::table('landing_pages')
            ->whereNull('header_title')
            ->update(['header_title' => DB::raw('headline')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('landing_pages', 'header_title')) {
            Schema::table('landing_pages', function (Blueprint $table): void {
                $table->dropColumn('header_title');
            });
        }
    }
};
