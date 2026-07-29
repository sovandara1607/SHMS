<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every "modal" view in this app (patient/appointment/medical-record show,
 * every *-form.blade.php, dashboard partials, etc. — ~35 views) is a bare
 * partial with no @extends('layouts.app'): it's only ever meant to be
 * fetched via the openModal() JS helper (which sends
 * X-Requested-With: XMLHttpRequest) and injected into an existing page's
 * modal container. Nothing stops a user from navigating to one of those
 * URLs directly — pasting it, bookmarking it, opening it in a new tab — and
 * when that happens the raw partial renders with no <head>, no CSS, no
 * sidebar/nav, no layout at all.
 *
 * Rather than adding an ajax-or-redirect-with-reopen-flash guard to ~30
 * controller methods individually, this catches it in one place: if a real
 * (non-ajax) GET request produces HTML that isn't a full page (no <html>
 * tag, meaning the layout was never applied), wrap it in a minimal page
 * shell instead of serving the broken fragment as-is.
 */
class WrapDirectlyNavigatedModalContent
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->ajax() || ! $request->isMethod('get') || ! $request->user()) {
            return $response;
        }

        if ($response->getStatusCode() !== 200 || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || str_contains($content, '<html')) {
            return $response;
        }

        $response->setContent(view('layouts.standalone-partial', ['content' => $content])->render());

        return $response;
    }
}
