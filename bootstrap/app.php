<?php
// bootstrap/app.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Laravel 11/12 middleware registration
//
// Older Laravel versions (≤10) registered middleware aliases in
// app/Http/Kernel.php's $routeMiddleware array. Laravel 11+
// removed that file — everything now configures through this
// bootstrap/app.php file using a fluent withMiddleware() call.
//
// ->alias() maps a short string (used in routes like
// ->middleware('role:admin')) to the actual middleware class.
// Without this registration, Laravel has no idea what 'role'
// or 'log.admin' mean when it sees them in routes/api.php.
// ─────────────────────────────────────────────────────────────

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:     __DIR__.'/../routes/web.php',
        api:     __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:  '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Register our custom middleware aliases.
        // These short names are what appear in route definitions:
        //   ->middleware('role:admin')
        //   ->middleware('log.admin')
        $middleware->alias([
            'role'      => \App\Http\Middleware\RoleMiddleware::class,
            'log.admin' => \App\Http\Middleware\LogAdminActivity::class,
        ]);

        // CORS must run on every API request, not just specific routes,
        // so we push it onto the global API middleware group.
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
