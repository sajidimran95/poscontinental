<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('sale') || $request->is('sale/*')) {
                return route('sale.login');
            }
            if ($request->is('customer') || $request->is('customer/*')) {
                return route('customer.login');
            }
            if ($request->is('delivery') || $request->is('delivery/*')) {
                return route('delivery.app.login');
            }

            return route('login');
        });
        $middleware->web(append: [
            \App\Http\Middleware\CaptureUserTimezone::class,
            \App\Http\Middleware\EagerLoadAuthenticatedUser::class,
        ]);
        $middleware->alias([
            'feature' => \App\Http\Middleware\EnsureFeatureAccess::class,
            'sale.app' => \App\Http\Middleware\EnsureSaleApp::class,
            'customer.app' => \App\Http\Middleware\EnsureCustomerApp::class,
            'delivery.app' => \App\Http\Middleware\EnsureDeliveryApp::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
