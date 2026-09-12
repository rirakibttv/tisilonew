<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SiteBranding
{
    public static function faviconUrl(): string
    {
        try {
            $faviconPath = ltrim((string) (SiteSetting::valuesFor('general')['favicon'] ?? ''), '/');
            $disk = Storage::disk('public');

            if (filled($faviconPath) && $disk->exists($faviconPath)) {
                return $disk->url($faviconPath).'?v='.substr(sha1($faviconPath), 0, 12);
            }
        } catch (Throwable) {
            // The panel must remain available before migrations or during storage outages.
        }

        return asset('favicon.svg');
    }
}
