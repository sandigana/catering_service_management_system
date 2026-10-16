<?php

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
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Only trust X-Forwarded-* headers from proxies you list (comma-separated IPs) in TRUSTED_PROXIES.
        // Needed behind a load balancer / Cloudflare so https links and client IPs are correct.
        // TRUSTED_PROXIES=* trusts the proxy in front of the app (needed on Render); otherwise a comma-separated IP list.
        $trusted = trim((string) env('TRUSTED_PROXIES', ''));
        $middleware->trustProxies(at: in_array($trusted, ['*', '**'], true)
            ? $trusted
            : array_filter(array_map('trim', explode(',', $trusted))));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
