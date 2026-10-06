<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Quote-accept URLs are client-specific signed links. Never let crawlers index them.
 */
class NoindexQuoteAccept
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
