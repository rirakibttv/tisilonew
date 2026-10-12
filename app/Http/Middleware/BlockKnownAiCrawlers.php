<?php

namespace App\Http\Middleware;

use App\Services\BotTrafficService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockKnownAiCrawlers
{
    public function __construct(private BotTrafficService $botTraffic) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->botTraffic->blockedAiCrawler($request->userAgent()) !== null) {
            return response('Automated AI crawler access is not permitted.', 403, [
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
        }

        return $next($request);
    }
}
