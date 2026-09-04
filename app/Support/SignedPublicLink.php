<?php

namespace App\Support;

use DateTimeInterface;

class SignedPublicLink
{
    /** Guest invoice / quotation / helper links stay usable without a year-long window. */
    public const TTL_DAYS = 14;

    public static function expiresAt(): DateTimeInterface
    {
        return now()->addDays(self::TTL_DAYS);
    }
}
