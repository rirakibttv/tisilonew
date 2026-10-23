<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $general = $this->values('general');
            $offers = $this->values('offers');

            $offers['hot_deal_end_date'] ??= $general['hot_deal_end_date'] ?? null;
            $offers['flash_sale_default_end_date'] ??= $general['flash_sale_end_date'] ?? null;

            unset(
                $general['hot_deal_end_date'],
                $general['flash_sale_end_date'],
                $general['reseller_enabled'],
                $general['reseller_deposit_min'],
                $general['reseller_deposit_max'],
                $general['reseller_wallet_min_balance'],
            );

            $this->store('general', $general);
            $this->store('offers', $offers);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $general = $this->values('general');
            $offers = $this->values('offers');

            $general['hot_deal_end_date'] ??= $offers['hot_deal_end_date'] ?? null;
            $general['flash_sale_end_date'] ??= $offers['flash_sale_default_end_date'] ?? null;
            unset($offers['hot_deal_end_date'], $offers['flash_sale_default_end_date']);

            $this->store('general', $general);
            $this->store('offers', $offers);
        });
    }

    /** @return array<string, mixed> */
    private function values(string $key): array
    {
        $raw = DB::table('site_settings')->where('key', $key)->value('values');
        $values = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($values) ? $values : [];
    }

    /** @param array<string, mixed> $values */
    private function store(string $key, array $values): void
    {
        $query = DB::table('site_settings')->where('key', $key);
        $data = ['values' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now()];

        if ($query->exists()) {
            $query->update($data);
        } else {
            DB::table('site_settings')->insert(['key' => $key, ...$data, 'created_at' => now()]);
        }

        SiteSetting::forget($key);
    }
};
