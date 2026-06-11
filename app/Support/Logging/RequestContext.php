<?php

namespace App\Support\Logging;

/**
 * Request-scoped logging context shared by HTTP middleware, Artisan, and Monolog processors.
 */
final class RequestContext
{
    /** @var array<string, mixed> */
    private static array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public static function set(array $context): void
    {
        self::$context = $context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function merge(array $context): void
    {
        self::$context = array_merge(self::$context, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$context;
    }

    public static function clear(): void
    {
        self::$context = [];
    }
}
