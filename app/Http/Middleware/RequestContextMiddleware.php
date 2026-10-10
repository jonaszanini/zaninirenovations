<?php

namespace App\Http\Middleware;

use App\Support\Logging\AppLogger;
use App\Support\Logging\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        RequestContext::set([
            'request_id' => $requestId,
            'channel' => 'http',
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => optional($request->route())->getName(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 200),
            'user_id' => optional($request->user())->id,
        ]);

        $request->attributes->set('request_id', $requestId);

        // Live previews are XHR. They must not eat the one-time success
        // flash that a Ctrl+S redirect is carrying to the next full page.
        if (($request->ajax() || $request->expectsJson()) && $request->hasSession()) {
            $request->session()->reflash();
        }

        $start = hrtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            AppLogger::info('http.request.completed', [
                'status' => 500,
                'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
                'uncaught' => true,
            ]);
            RequestContext::clear();

            throw $e;
        }

        AppLogger::info('http.request.completed', [
            'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
        ]);

        $response->headers->set('X-Request-Id', $requestId);
        RequestContext::clear();

        return $response;
    }
}
