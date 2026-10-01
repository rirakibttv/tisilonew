<?php

use App\Http\Middleware\SetStorefrontLocale;
use App\Support\CanonicalUrl;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            CanonicalUrl::class,
        ]);

        $middleware->web(append: [
            SetStorefrontLocale::class,
        ]);

        $middleware->redirectGuestsTo(fn (): string => route('store.account.login'));
        $middleware->redirectUsersTo(fn (): string => route('store.account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
