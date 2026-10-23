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
            $seo = $this->values('seo');
            $pages = $this->values('pages');

            $general['meta_description'] = $general['meta_description'] ?? $general['footer_about_text'] ?? '';
            $general['meta_keywords'] = $general['meta_keywords'] ?? $seo['meta_keywords'] ?? $seo['meta_tags'] ?? '';
            $general['search_console_verification'] = $general['search_console_verification'] ?? $seo['search_console_verification'] ?? '';

            $legacyCheckoutNote = $general['checkout_note'] ?? null;
            $legacyOrderPolicy = $general['order_policy'] ?? null;
            unset($general['checkout_note'], $general['order_policy']);

            foreach ([
                [
                    'name' => 'Policies and Notes',
                    'title' => 'Policies and Notes',
                    'slug' => 'policies-and-notes',
                    'description' => $legacyCheckoutNote,
                    'status' => filled($legacyCheckoutNote),
                ],
                [
                    'name' => 'Order Policy',
                    'title' => 'Order Policy',
                    'slug' => 'order-policy',
                    'description' => $legacyOrderPolicy,
                    'status' => filled($legacyOrderPolicy),
                ],
            ] as $page) {
                $index = collect($pages['pages'] ?? [])->search(fn (array $existing): bool => ($existing['slug'] ?? null) === $page['slug']);

                if ($index === false) {
                    $pages['pages'][] = $page;
                } elseif (blank($pages['pages'][$index]['description'] ?? null) && filled($page['description'])) {
                    $pages['pages'][$index]['description'] = $page['description'];
                    $pages['pages'][$index]['status'] = true;
                }
            }

            unset($seo['meta_tags'], $seo['meta_keywords'], $seo['search_console_verification']);

            $this->store('general', $general);
            $this->store('seo', $seo);
            $this->store('pages', $pages);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $general = $this->values('general');
            $seo = $this->values('seo');
            $pages = $this->values('pages');

            $seo['meta_tags'] = $general['meta_keywords'] ?? $seo['meta_tags'] ?? '';
            $seo['search_console_verification'] = $general['search_console_verification'] ?? $seo['search_console_verification'] ?? '';
            $general['checkout_note'] = $general['checkout_note'] ?? collect($pages['pages'] ?? [])->firstWhere('slug', 'policies-and-notes')['description'] ?? null;
            $general['order_policy'] = $general['order_policy'] ?? collect($pages['pages'] ?? [])->firstWhere('slug', 'order-policy')['description'] ?? null;
            unset($general['meta_description'], $general['meta_keywords'], $general['search_console_verification']);

            $this->store('general', $general);
            $this->store('seo', $seo);
            $this->store('pages', $pages);
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
