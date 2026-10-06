<?php

namespace App\Console\Commands;

use App\Services\MetaCatalogService;
use Illuminate\Console\Command;
use Throwable;

class SyncMetaCatalog extends Command
{
    protected $signature = 'meta-catalog:sync';

    protected $description = 'Queue the current Tisilo product and category feed for Meta Catalog ingestion';

    public function handle(MetaCatalogService $catalog): int
    {
        try {
            if (! $catalog->syncWhenConfigured()) {
                $this->components->info('Meta Catalog is disabled or incomplete; nothing was sent.');

                return self::SUCCESS;
            }

            $this->components->info('Meta Catalog feed upload queued successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
