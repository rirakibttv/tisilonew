<?php

namespace App\Http\Middleware;

use App\Services\BotTrafficControlService;
use App\Services\VisitorAnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackBotTraffic
{
    public function __construct(
        private VisitorAnalyticsService $analytics,
        private BotTrafficControlService $trafficControl,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();
        $isStorefront = str_starts_with($routeName, 'store.');

        if ($isStorefront && $this->trafficControl->blockedBy($request) !== null) {
            return response('Access denied by Tisilo traffic security.', 403, [
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        $response = $next($request);

        if ($request->isMethod('GET')
            && $response->getStatusCode() < 400
            && $isStorefront) {
            try {
                $this->analytics->recordBotRequest($request);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }
}
