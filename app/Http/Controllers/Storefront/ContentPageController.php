<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;

class ContentPageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = collect(SiteSetting::valuesFor('pages')['pages'] ?? [])
            ->first(fn (array $page): bool => ($page['slug'] ?? null) === $slug && ($page['status'] ?? false));

        abort_unless($page, 404);

        return view('storefront.pages.show', compact('page'));
    }
}
