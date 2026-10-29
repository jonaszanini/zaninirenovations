<?php

namespace App\Support;

/**
 * @internal Result of one pinned cURL attempt (production uses ext-curl; tests may stub).
 */
final class PinnedCurlAttemptResult
{
    /**
     * @param  array<string, list<string>|string>  $headers
     */
    public function __construct(
        public readonly bool $ok,
        public readonly ?int $status = null,
        public readonly array $headers = [],
        public readonly string $body = '',
        public readonly ?string $failureCode = null,
        public readonly ?string $curlError = null,
        public readonly ?int $curlErrno = null,
    ) {}
}
