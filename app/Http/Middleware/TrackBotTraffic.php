<?php

namespace App\Http\Middleware;

use App\Services\VisitorAnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackBotTraffic
{
    public function __construct(private VisitorAnalyticsService $analytics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $routeName = (string) $request->route()?->getName();

        if ($request->isMethod('GET')
            && $response->getStatusCode() < 400
            && str_starts_with($routeName, 'store.')) {
            try {
                $this->analytics->recordBotRequest($request);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }
}
