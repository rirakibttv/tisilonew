<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class SitemapService
{
    public function __construct(private GoogleSearchConsoleService $searchConsole) {}

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $seo = SiteSetting::valuesFor('seo');
        $legacy = SiteSetting::valuesFor('sitemap');

        return [
            ...$seo,
            'search_console_sitemap_url' => $seo['search_console_sitemap_url'] ?? url('/sitemap.xml'),
            'sitemap_include_products' => $seo['sitemap_include_products'] ?? $legacy['include_products'] ?? true,
            'sitemap_include_categories' => $seo['sitemap_include_categories'] ?? true,
            'sitemap_include_pages' => $seo['sitemap_include_pages'] ?? $legacy['include_pages'] ?? true,
            'sitemap_change_frequency' => $seo['sitemap_change_frequency'] ?? $legacy['change_frequency'] ?? 'hourly',
        ];
    }

    /**
     * Generate the public sitemap and submit it to Google when its content changes.
     *
     * @return array{changed: bool, generated_at: string, path: string, sitemap_url: string, submitted: bool, submission_status: string, url_count: int}
     */
    public function sync(): array
    {
        $result = $this->generate();

        if (! $result['changed']) {
            $status = 'Up to date — Google submission not required';
            $this->storeStatus(['sitemap_last_auto_submission_status' => $status]);

            return [...$result, 'submitted' => false, 'submission_status' => $status];
        }

        $settings = $this->settings();
        $serviceAccount = trim((string) (SiteSetting::secretsFor('seo')['search_console_service_account_json'] ?? ''));

        if (! $this->boolean($settings['search_console_enabled'] ?? false)
            || blank($settings['search_console_property_url'] ?? null)
            || $serviceAccount === '') {
            $status = 'Sitemap generated; waiting for Search Console credentials';
            $this->storeStatus(['sitemap_last_auto_submission_status' => $status]);

            return [...$result, 'submitted' => false, 'submission_status' => $status];
        }

        $sitemapUrl = trim((string) ($settings['search_console_sitemap_url'] ?? ''));
        if (! filter_var($sitemapUrl, FILTER_VALIDATE_URL)) {
            $this->storeStatus(['sitemap_last_auto_submission_status' => 'Failed — invalid sitemap URL']);

            throw new RuntimeException('Save a valid sitemap URL in Google Search Console settings.');
        }

        try {
            $this->searchConsole->submitSitemap(
                $serviceAccount,
                (string) $settings['search_console_property_url'],
                $sitemapUrl,
            );

            $status = 'Submitted automatically after sitemap update';
            $this->storeStatus([
                'search_console_last_sitemap_submitted_at' => now()->toIso8601String(),
                'sitemap_last_auto_submission_status' => $status,
            ]);

            return [...$result, 'submitted' => true, 'submission_status' => $status];
        } catch (Throwable $exception) {
            $this->storeStatus([
                'sitemap_last_auto_submission_status' => 'Failed — '.$exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /** @return array{changed: bool, generated_at: string, path: string, sitemap_url: string, url_count: int} */
    public function generate(): array
    {
        $settings = $this->settings();
        $frequency = $this->frequency((string) $settings['sitemap_change_frequency']);
        $urls = collect([
            ['loc' => route('store.home'), 'priority' => '1.0'],
            ['loc' => route('store.shop.index'), 'priority' => '0.9'],
        ]);

        if ($this->boolean($settings['sitemap_include_products'])) {
            Product::query()
                ->where('status', 'published')
                ->select(['slug'])
                ->orderBy('id')
                ->each(fn (Product $product) => $urls->push([
                    'loc' => route('store.products.show', ['product' => $product->slug]),
                    'priority' => '0.8',
                ]));
        }

        if ($this->boolean($settings['sitemap_include_categories'])) {
            $this->categoryUrls()->each(fn (string $url) => $urls->push([
                'loc' => $url,
                'priority' => '0.7',
            ]));
        }

        if ($this->boolean($settings['sitemap_include_pages'])) {
            collect(SiteSetting::valuesFor('pages')['pages'] ?? [])
                ->filter(fn (array $page): bool => $this->boolean($page['status'] ?? false) && filled($page['slug'] ?? null))
                ->each(fn (array $page) => $urls->push([
                    'loc' => $this->pageUrl((string) $page['slug']),
                    'priority' => '0.6',
                ]));
        }

        $urls = $urls->unique('loc')->values();
        $body = $urls->map(function (array $url) use ($frequency): string {
            $location = htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

            return "  <url>\n    <loc>{$location}</loc>\n    <changefreq>{$frequency}</changefreq>\n    <priority>{$url['priority']}</priority>\n  </url>";
        })->implode("\n");
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$body}\n</urlset>\n";
        $path = public_path('sitemap.xml');
        $changed = ! File::exists($path) || hash('sha256', (string) File::get($path)) !== hash('sha256', $xml);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $xml, true);

        $generatedAt = now()->toIso8601String();
        $status = [
            'search_console_sitemap_url' => $settings['search_console_sitemap_url'],
            'sitemap_include_products' => $settings['sitemap_include_products'],
            'sitemap_include_categories' => $settings['sitemap_include_categories'],
            'sitemap_include_pages' => $settings['sitemap_include_pages'],
            'sitemap_change_frequency' => $frequency,
            'sitemap_last_generated_at' => $generatedAt,
            'sitemap_last_generated_status' => 'Generated successfully',
            'sitemap_url_count' => $urls->count(),
        ];
        if ($changed) {
            $status['sitemap_last_changed_at'] = $generatedAt;
        }
        $this->storeStatus($status);

        return [
            'changed' => $changed,
            'generated_at' => $generatedAt,
            'path' => $path,
            'sitemap_url' => (string) $settings['search_console_sitemap_url'],
            'url_count' => $urls->count(),
        ];
    }

    /** @return Collection<int, string> */
    private function categoryUrls(): Collection
    {
        $categories = Category::query()
            ->where('status', true)
            ->select(['id', 'parent_id', 'slug'])
            ->get();
        $byId = $categories->keyBy('id');

        return $categories->map(function (Category $category) use ($byId): ?string {
            $segments = [];
            $visited = [];
            $current = $category;

            while ($current instanceof Category && ! isset($visited[$current->getKey()])) {
                $visited[$current->getKey()] = true;
                array_unshift($segments, $current->slug);

                if ($current->parent_id === null) {
                    break;
                }

                $parent = $byId->get($current->parent_id);
                if (! $parent instanceof Category) {
                    return null;
                }
                $current = $parent;
            }

            if ($segments === [] || $current->parent_id !== null) {
                return null;
            }

            $categorySlug = array_shift($segments);

            return rtrim(route('store.categories.show', [
                'categorySlug' => $categorySlug,
                'categoryPath' => $segments === [] ? null : implode('/', $segments),
            ]), '/');
        })->filter()->values();
    }

    private function pageUrl(string $slug): string
    {
        return match ($slug) {
            'contact-us' => route('store.contact'),
            'about-us' => route('store.about'),
            'blog' => route('store.blog'),
            default => route('store.pages.show', ['slug' => $slug]),
        };
    }

    /** @param array<string, mixed> $status */
    private function storeStatus(array $status): void
    {
        SiteSetting::put('seo', [
            ...SiteSetting::valuesFor('seo'),
            ...$status,
        ]);
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function frequency(string $frequency): string
    {
        return in_array($frequency, ['always', 'hourly', 'daily', 'weekly', 'monthly'], true)
            ? $frequency
            : 'hourly';
    }
}
