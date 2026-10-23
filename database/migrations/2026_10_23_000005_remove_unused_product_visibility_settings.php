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
            unset($general['show_all_products'], $general['show_category_wise_products']);
            $this->store('general', $general);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $general = $this->values('general');
            $general['show_all_products'] ??= true;
            $general['show_category_wise_products'] ??= true;
            $this->store('general', $general);
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
