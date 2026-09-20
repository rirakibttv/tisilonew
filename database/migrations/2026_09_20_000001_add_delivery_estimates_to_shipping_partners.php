<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shipping_partners', 'estimated_min_days')) {
            Schema::table('shipping_partners', function (Blueprint $table): void {
                $table->unsignedSmallInteger('estimated_min_days')->default(1)->after('api_provider');
            });
        }

        if (! Schema::hasColumn('shipping_partners', 'estimated_max_days')) {
            Schema::table('shipping_partners', function (Blueprint $table): void {
                $table->unsignedSmallInteger('estimated_max_days')->default(3)->after('estimated_min_days');
            });
        }

        if (! Schema::hasTable('shipping_region_rates')) {
            return;
        }

        $estimates = DB::table('shipping_region_rates')
            ->whereNotNull('shipping_partner_id')
            ->selectRaw('shipping_partner_id, MIN(estimated_min_days) as min_days, MAX(estimated_max_days) as max_days')
            ->groupBy('shipping_partner_id')
            ->get();

        foreach ($estimates as $estimate) {
            $minDays = max(1, (int) $estimate->min_days);

            DB::table('shipping_partners')
                ->where('id', $estimate->shipping_partner_id)
                ->update([
                    'estimated_min_days' => $minDays,
                    'estimated_max_days' => max($minDays, (int) $estimate->max_days),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $columns = collect(['estimated_min_days', 'estimated_max_days'])
            ->filter(fn (string $column): bool => Schema::hasColumn('shipping_partners', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('shipping_partners', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
