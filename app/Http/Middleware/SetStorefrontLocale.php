<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    private const DEFAULT_LOCALE = 'en';

    /**
     * Apply the visitor's saved storefront language to the current request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || ! str_starts_with($routeName, 'store.')) {
            return $next($request);
        }

        $locale = $request->session()->get('storefront_locale', self::DEFAULT_LOCALE);

        if (! in_array($locale, ['bn', 'en'], true)) {
            $locale = self::DEFAULT_LOCALE;
            $request->session()->forget('storefront_locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
