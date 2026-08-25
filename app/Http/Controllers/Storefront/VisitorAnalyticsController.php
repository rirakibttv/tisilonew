<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\VisitorAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VisitorAnalyticsController extends Controller
{
    public function __invoke(Request $request, VisitorAnalyticsService $analytics): Response
    {
        if ($analytics->isBot($request->userAgent())) {
            return response()->noContent();
        }

        $data = $request->validate([
            'visitor_id' => ['nullable', 'uuid'],
            'event_type' => ['required', Rule::in(VisitorAnalyticsService::EVENT_TYPES)],
            'event_id' => ['nullable', 'string', 'max:100'],
            'path' => ['nullable', 'string', 'max:1000'],
            'referrer' => ['nullable', 'string', 'max:1000'],
            'attribution' => ['nullable', 'array'],
            'attribution.source' => ['nullable', 'string', 'max:100'],
            'attribution.medium' => ['nullable', 'string', 'max:100'],
            'attribution.campaign' => ['nullable', 'string', 'max:191'],
            'attribution.content' => ['nullable', 'string', 'max:191'],
            'attribution.term' => ['nullable', 'string', 'max:191'],
            'attribution.click_source' => ['nullable', Rule::in(['facebook', 'google'])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'metadata' => ['nullable', 'array', 'max:10'],
        ]);

        try {
            $event = $analytics->record($request, $data);

            return response()->noContent()->cookie(
                'tisilo_vid', $event->visitor_id, 60 * 24 * 365, '/', null,
                $request->isSecure(), false, false, 'lax',
            );
        } catch (Throwable) {
            return response()->noContent();
        }
    }
}
