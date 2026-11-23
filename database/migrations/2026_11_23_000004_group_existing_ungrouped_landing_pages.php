<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landing_page_groups')
            || ! Schema::hasTable('landing_pages')
            || ! Schema::hasColumn('landing_pages', 'group_id')) {
            return;
        }

        if (! DB::table('landing_pages')->whereNull('group_id')->exists()) {
            return;
        }

        $groupId = DB::table('landing_page_groups')->where('slug', 'ungrouped-ads')->value('id');

        if (! $groupId) {
            $groupId = DB::table('landing_page_groups')->insertGetId([
                'name' => 'Ungrouped Ads',
                'slug' => 'ungrouped-ads',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('landing_pages')->whereNull('group_id')->update([
            'group_id' => $groupId,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep page assignments intact when rolling this data migration back.
    }
};
