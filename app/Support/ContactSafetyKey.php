<?php

namespace App\Support;

/**
 * Per-session contact-form token (replaces the static JS key).
 */
class ContactSafetyKey
{
    public static function token(): string
    {
        return hash_hmac('sha256', (string) csrf_token(), (string) config('app.key'));
    }

    public static function check(?string $provided): bool
    {
        if (! is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals(self::token(), $provided);
    }
}
