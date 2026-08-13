<?php

namespace App\Support;

/**
 * Query flag for printable quotation shopping lists.
 *
 * Full list ({@see showsPrices} true): unit price and line total.
 * Simple list: quantities and item names only.
 */
final class PurchaseListPrintMode
{
    /**
     * @param  mixed  $queryValue  Request `prices` query (1/0, full/simple, …).
     */
    public static function showsPrices(mixed $queryValue): bool
    {
        if ($queryValue === null || $queryValue === '') {
            return true;
        }

        $normalized = strtolower(trim((string) $queryValue));

        return ! in_array($normalized, ['0', 'false', 'simple', 'no', 'off'], true);
    }
}
