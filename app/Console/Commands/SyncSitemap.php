<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;
use Throwable;

class SyncSitemap extends Command
{
    protected $signature = 'sitemap:sync';

    protected $description = 'Regenerate the public sitemap and submit changed content to Google Search Console';

    public function handle(SitemapService $sitemap): int
    {
        try {
            $result = $sitemap->sync();
            $change = $result['changed'] ? 'updated' : 'unchanged';

            $this->components->info(
                "Sitemap {$change}: {$result['url_count']} URLs. {$result['submission_status']}.",
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
