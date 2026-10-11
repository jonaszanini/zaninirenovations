<?php

namespace App\Http\Middleware;

use SessionHandlerInterface;

/**
 * Drops session writes. Used for quotation preview requests so they cannot
 * age or extend one-time flash messages.
 */
final class ReadOnlySessionHandler implements SessionHandlerInterface
{
    public function __construct(private SessionHandlerInterface $inner)
    {
    }

    public function open($path, $name): bool
    {
        return $this->inner->open($path, $name);
    }

    public function close(): bool
    {
        return $this->inner->close();
    }

    public function read($id): string|false
    {
        return $this->inner->read($id);
    }

    public function write($id, $data): bool
    {
        return true;
    }

    public function destroy($id): bool
    {
        return $this->inner->destroy($id);
    }

    public function gc($max_lifetime): int|false
    {
        return $this->inner->gc($max_lifetime);
    }
}
