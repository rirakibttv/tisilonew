<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RestoreProductImageAliases extends Command
{
    protected $signature = 'media:restore-product-image-aliases';

    protected $description = 'Restore missing SEO-named product image copies from preserved uploads';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $restored = 0;
        $skipped = 0;

        foreach ((array) config('product_image_recovery.aliases', []) as $target => $source) {
            if (! $this->isSafeRelativePath($target) || ! $this->isSafeRelativePath($source)) {
                $this->warn('Skipped an invalid product image recovery path.');
                $skipped++;

                continue;
            }

            if ($disk->exists($target)) {
                $skipped++;

                continue;
            }

            if (! $disk->exists($source)) {
                $this->warn("Source image is unavailable; left untouched: {$source}");
                $skipped++;

                continue;
            }

            if ($disk->copy($source, $target) && $disk->exists($target)) {
                $restored++;
                $this->line("Restored product image alias: {$target}");
            } else {
                $this->warn("Could not restore product image alias: {$target}");
                $skipped++;
            }
        }

        $this->info("Product image recovery finished: {$restored} restored, {$skipped} already present or unavailable.");

        return self::SUCCESS;
    }

    private function isSafeRelativePath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, "\0")
            && ! str_starts_with($path, '/')
            && ! preg_match('/^[A-Za-z]:/', $path)
            && ! in_array('..', explode('/', str_replace('\\', '/', $path)), true);
    }
}
