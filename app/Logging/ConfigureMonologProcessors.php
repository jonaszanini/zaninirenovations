<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Laravel log channel tap — attaches processors to every Monolog handler on a channel.
 */
final class ConfigureMonologProcessors
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        foreach ($monolog->getHandlers() as $handler) {
            $handler->pushProcessor(new RequestContextProcessor);
            $handler->pushProcessor(new RedactionProcessor);
        }
    }
}
