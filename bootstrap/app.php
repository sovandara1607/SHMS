<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register the short alias used on routes: ->middleware('permission:...')
        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // RBAC failures (403 / authorization) → the Unauthorized page,
        // satisfying "unauthorized users must be redirected to /unauthorized".
        $exceptions->render(function (\Throwable $e, Request $request) {
            $is403 = $e instanceof AuthorizationException
                || ($e instanceof HttpException && $e->getStatusCode() === 403);
            if ($is403 && ! $request->is('api/*') && $request->user()) {
                return response()->view('errors.unauthorized', [], 403);
            }
            return null;
        });
    })->create();
