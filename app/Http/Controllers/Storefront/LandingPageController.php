<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\SiteSetting;
use App\Services\VisitorAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Throwable;

class LandingPageController extends Controller
{
    public function show(Request $request, LandingPage $landingPage, VisitorAnalyticsService $analytics): View
    {
        abort_unless(
            LandingPage::query()->published()->whereKey($landingPage->getKey())->exists(),
            404,
        );

        return $this->render($request, $landingPage, $analytics);
    }

    public function preview(Request $request, LandingPage $landingPage, VisitorAnalyticsService $analytics): View
    {
        return $this->render($request, $landingPage, $analytics, true);
    }

    private function render(
        Request $request,
        LandingPage $landingPage,
        VisitorAnalyticsService $analytics,
        bool $preview = false,
    ): View {
        $landingPage->load([
            'products' => fn ($query) => $query->where('status', 'published'),
            'products.variations.attributeValues.attribute:id,name',
        ]);
        abort_if($landingPage->products->isEmpty(), 404, 'This landing page has no published products.');

        $primaryProduct = $landingPage->products->first();

        if (! $preview) {
            try {
                $analytics->record($request, [
                    'event_type' => 'product_view',
                    'product_id' => $primaryProduct->getKey(),
                    'value' => $primaryProduct->sale_price ?? $primaryProduct->regular_price,
                    'metadata' => [
                        'product_name' => $primaryProduct->name,
                        'currency' => 'BDT',
                    ],
                ]);
            } catch (Throwable) {
                // Analytics must never block a paid-ad landing page.
            }
        }

        return view('storefront.landing.show', [
            'landingPage' => $landingPage,
            'primaryProduct' => $primaryProduct,
            'preview' => $preview,
            'generalSettings' => SiteSetting::valuesFor('general'),
            'contactSettings' => SiteSetting::valuesFor('contact'),
            'seoSettings' => SiteSetting::valuesFor('seo'),
        ]);
    }
}
