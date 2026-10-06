<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\MetaCatalogService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaCatalogFeedController extends Controller
{
    public function __invoke(string $token, MetaCatalogService $catalog): StreamedResponse|Response
    {
        $values = SiteSetting::valuesFor('facebook_catalog');
        $savedToken = (string) (SiteSetting::secretsFor('facebook_catalog')['feed_token'] ?? '');

        abort_unless(
            filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL)
                && $savedToken !== ''
                && hash_equals($savedToken, $token),
            404,
        );

        return response()->stream(function () use ($catalog): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, MetaCatalogService::FEED_COLUMNS, "\t", '"', '');

            foreach ($catalog->items() as $item) {
                fputcsv(
                    $handle,
                    array_map(fn (string $column): string|int => $item[$column] ?? '', MetaCatalogService::FEED_COLUMNS),
                    "\t",
                    '"',
                    '',
                );
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="tisilo-meta-catalog.tsv"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
