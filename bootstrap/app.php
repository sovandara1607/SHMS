<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

        // Traffic arrives either straight from the LAN or via the Cloudflare
        // Tunnel container on the same Docker network — trusting all proxies
        // lets Laravel read X-Forwarded-Proto from cloudflared so url()/
        // isSecure() reflect the real https:// the visitor used, instead of
        // the plain http:// cloudflared -> database-final hop.
        $middleware->trustProxies(at: '*');

        // ~35 views across this app are bare partials meant only to be
        // fetched into a modal via JS — see the class docblock. This wraps
        // any of them that get hit by a direct browser navigation instead.
        $middleware->web(append: [
            \App\Http\Middleware\WrapDirectlyNavigatedModalContent::class,
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

        // central-service failures bubble up here as an uncaught
        // ConnectionException (service unreachable) or RequestException
        // (4xx/5xx from ->throw()) whenever a controller doesn't already
        // have a specific status check for them. Left alone, Laravel would
        // render its debug/error page including the raw response body —
        // which can carry central-service's internal exception message and
        // stack trace straight through to the browser. Show a generic flash
        // instead and keep the real detail in the log.
        $exceptions->render(function (ConnectionException $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
            report($e);

            return back()->with('error', 'The service is temporarily unavailable. Please try again shortly.');
        });

        $exceptions->render(function (RequestException $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
            report($e);

            return back()->with('error', 'Something went wrong processing your request. Please try again.');
        });
    })->create();
