<?php

namespace App\Support;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalRoot = rtrim((string) config('app.canonical_url'), '/');

        if ($canonicalRoot === '' || ! $request->isMethodSafe()) {
            return $next($request);
        }

        $canonicalHost = parse_url($canonicalRoot, PHP_URL_HOST);
        $canonicalScheme = parse_url($canonicalRoot, PHP_URL_SCHEME);

        if (! is_string($canonicalHost) || ! is_string($canonicalScheme)) {
            return $next($request);
        }

        if (
            strcasecmp($request->getHost(), $canonicalHost) === 0
            && strcasecmp($request->getScheme(), $canonicalScheme) === 0
        ) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $target = $canonicalRoot.($path === '/' ? '' : $path);

        if (filled($request->getQueryString())) {
            $target .= '?'.$request->getQueryString();
        }

        return new RedirectResponse($target, 301);
    }
}
