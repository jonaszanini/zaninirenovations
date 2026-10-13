<?php

namespace App\Support\Quotations;

/**
 * Client PDF commute/debris fold versions.
 * 1 = legacy (extras may land on the first taxed line).
 * 2 = #60 fold (live since ~01:20; frozen byte-for-byte).
 * 3 = current (labour-category / calculator HST / override footer).
 *
 * Stored on a class because constants inside a trait require PHP 8.2, and
 * this app still runs on PHP 8.0.
 */
final class PdfFoldVersion
{
    public const LEGACY = 1;

    public const V2 = 2;

    public const CURRENT = 3;
}
