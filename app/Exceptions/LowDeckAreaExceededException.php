<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a requested deck footprint exceeds the maximum area allowed
 * by the Ontario Building Code guardrail (default 592 sq ft / 55 m²).
 */
class LowDeckAreaExceededException extends RuntimeException
{
    public float $areaSqFt;

    public float $maxAreaSqFt;

    public function __construct(float $areaSqFt, float $maxAreaSqFt)
    {
        $this->areaSqFt = $areaSqFt;
        $this->maxAreaSqFt = $maxAreaSqFt;

        parent::__construct(sprintf(
            'Deck area %s sq ft exceeds the %s sq ft maximum for a floating deck. Split the deck or reduce the dimensions.',
            rtrim(rtrim(number_format($areaSqFt, 2), '0'), '.'),
            rtrim(rtrim(number_format($maxAreaSqFt, 2), '0'), '.')
        ));
    }
}
