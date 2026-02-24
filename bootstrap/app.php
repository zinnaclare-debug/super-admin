<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Token-only API auth (no cookie/session auth) to allow concurrent
        // multi-role logins on the same browser/device without role collision.
        $middleware->appendToGroup('api', \App\Http\Middleware\ResolveTenantFromSubdomain::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureUserBelongsToTenant::class);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->alias([
            'feature' => \App\Http\Middleware\CheckFeature::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'role_feature' => \App\Http\Middleware\RoleFeaturePermission::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
