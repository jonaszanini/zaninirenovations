<?php

namespace App\Logging;

use App\Support\Logging\RequestContext;
use Monolog\Processor\ProcessorInterface;

/**
 * Injects shared request/CLI context into every log record's extra fields.
 */
final class RequestContextProcessor implements ProcessorInterface
{
    public function __invoke(array $record): array
    {
        $context = RequestContext::all();

        if ($context === []) {
            return $record;
        }

        $record['extra'] = array_merge($record['extra'] ?? [], $context);

        return $record;
    }
}
