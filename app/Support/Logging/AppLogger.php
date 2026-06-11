<?php

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Central application logger — structured context, exception enrichment, PSR-3 levels.
 */
final class AppLogger
{
    public static function emergency(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::emergency($message, self::enrich($context, $e));
    }

    public static function alert(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::alert($message, self::enrich($context, $e));
    }

    public static function critical(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::critical($message, self::enrich($context, $e));
    }

    public static function error(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::error($message, self::enrich($context, $e));
    }

    public static function warning(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::warning($message, self::enrich($context, $e));
    }

    public static function notice(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::notice($message, self::enrich($context, $e));
    }

    public static function info(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::info($message, self::enrich($context, $e));
    }

    public static function debug(string $message, array $context = [], ?Throwable $e = null): void
    {
        Log::debug($message, self::enrich($context, $e));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function enrich(array $context, ?Throwable $e): array
    {
        if ($e === null) {
            return $context;
        }

        return $context + [
            'exception' => [
                'class' => $e::class,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ];
    }
}
