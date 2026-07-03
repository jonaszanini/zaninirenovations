<?php

namespace Tests\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

final class RouteSmokeHelper
{
    /** @var list<string> */
    private const SKIP_ROUTE_NAMES = [
        'ignition.healthCheck',
        'sanctum.csrf-cookie',
        'login',
        'admin.google-photos.callback',
        'admin.google-photos.picker-sessions.show',
        'admin.google-photos.picker-sessions.media',
        'admin.google-photos.thumbnail',
        'public.client-project.invoice-pdf',
        'public.client-project.receipt-pdf',
        'public.client-project.quotation-pdf',
        'public.helper-payment.acknowledge',
        'cron.run',
    ];

    /** @var list<string> */
    private const SKIP_URI_PREFIXES = [
        '_ignition/',
        'api/',
    ];

    private SmokeTestFixtures $fixtures;

    public function __construct(?SmokeTestFixtures $fixtures = null)
    {
        $this->fixtures = $fixtures ?? new SmokeTestFixtures();
    }

    /**
     * @return list<array{route: Route, url: string}>
     */
    public function getGetRoutes(bool $adminOnly, bool $publicOnly): array
    {
        $routes = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $this->isGetRoute($route)) {
                continue;
            }

            if ($this->shouldSkip($route)) {
                continue;
            }

            $isAdmin = $this->isAdminRoute($route);

            if ($adminOnly && ! $isAdmin) {
                continue;
            }

            if ($publicOnly && $isAdmin) {
                continue;
            }

            try {
                $routes[] = [
                    'route' => $route,
                    'url' => $this->urlForRoute($route),
                ];
            } catch (\InvalidArgumentException) {
                // Parameter we cannot resolve yet — skip until a fixture exists.
            }
        }

        return $routes;
    }

    private function isGetRoute(Route $route): bool
    {
        return in_array('GET', $route->methods(), true);
    }

    private function shouldSkip(Route $route): bool
    {
        $name = (string) $route->getName();
        if ($name !== '' && in_array($name, self::SKIP_ROUTE_NAMES, true)) {
            return true;
        }

        $uri = $route->uri();
        foreach (self::SKIP_URI_PREFIXES as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isAdminRoute(Route $route): bool
    {
        $name = (string) $route->getName();
        if (str_starts_with($name, 'admin.')) {
            return true;
        }

        $uri = $route->uri();

        return str_starts_with($uri, 'admin/')
            || str_starts_with($uri, 'admin')
            || $uri === 'dashboard';
    }

    private function urlForRoute(Route $route): string
    {
        $name = $route->getName();
        $parameters = $this->resolveParameters($route);

        if ($name) {
            return route($name, $parameters, false);
        }

        $uri = $route->uri();
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{'.$key.'}', (string) $value, $uri);
            $uri = preg_replace('/\{'.$key.':[^}]+\}/', (string) $value, $uri) ?? $uri;
        }

        return '/'.ltrim($uri, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveParameters(Route $route): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[$name] = $this->fixtures->resolve($name, $route);
        }

        return $parameters;
    }

    public static function routeLabel(Route $route, string $url): string
    {
        $name = $route->getName();

        return $name ? "{$name} ({$url})" : "{$url}";
    }

    public static function formatFailure(Route $route, string $url, TestResponse $response): string
    {
        $status = $response->getStatusCode();
        $label = self::routeLabel($route, $url);
        $hint = self::responseHint($response);

        return $hint !== ''
            ? "{$label} returned HTTP {$status} — {$hint}"
            : "{$label} returned HTTP {$status}";
    }

    private static function responseHint(TestResponse $response): string
    {
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return '';
        }

        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $matches)) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');

        return Str::limit($plain, 120, '…');
    }
}
