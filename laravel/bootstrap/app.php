<?php

use App\Http\Middleware\EnsurePiTenantAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['pi.tenant' => EnsurePiTenantAccess::class]);
        // This app uses a static sign-in page, not a named Laravel "login" route.
        // Keep unauthenticated API requests as JSON 401 via shouldRenderJsonWhen.
        $middleware->redirectGuestsTo('/login.html');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
