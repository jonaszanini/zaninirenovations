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

        $this->skipSessionWriteForPreview($request);

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

    /**
     * Preview posts must not persist the session. A write ages one-time
     * messages, and reflash() would keep errors and old input for a later page.
     */
    private function skipSessionWriteForPreview(Request $request): void
    {
        $name = optional($request->route())->getName();
        if (! is_string($name) || ! $this->isQuotationPreviewRoute($name) || ! $request->hasSession()) {
            return;
        }
        $session = $request->session();
        $handlerProp = new \ReflectionProperty($session, 'handler');
        $handlerProp->setAccessible(true);
        $handler = $handlerProp->getValue($session);
        if ($handler instanceof \SessionHandlerInterface && ! $handler instanceof ReadOnlySessionHandler) {
            $handlerProp->setValue($session, new ReadOnlySessionHandler($handler));
        }
    }

    private function isQuotationPreviewRoute(string $name): bool
    {
        if (! str_starts_with($name, 'admin.quotations.')) {
            return false;
        }

        return str_ends_with($name, '.calculate')
            || str_ends_with($name, '.calculate-existing')
            || str_ends_with($name, '.preview')
            || str_ends_with($name, '.live');
    }
}
