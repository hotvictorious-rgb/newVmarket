<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->append(\App\Http\Middleware\SanitizeAndHardenInputMiddleware::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'admin.session' => \App\Http\Middleware\AdminSessionSecurityMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'payment/paystack/webhook',
            'api/v1/payment/paystack/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
