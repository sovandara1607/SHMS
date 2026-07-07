<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for RBAC. Usage:  ->middleware('permission:patient.view')
 * Denied requests abort 403, which the exception handler renders as the
 * Unauthorized page. Must run after 'auth'.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user() || Gate::denies($permission)) {
            abort(403);
        }
        return $next($request);
    }
}
