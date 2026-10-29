<?php

namespace App\Support;

use Illuminate\Http\Client\Response;

final class SafeOutboundWebFetchResult
{
    public function __construct(
        public readonly ?Response $response,
        public readonly ?string $failureCode = null,
        public readonly ?string $userMessage = null,
        public readonly ?string $logDetail = null,
    ) {}

    public function ok(): bool
    {
        return $this->response !== null;
    }
}
