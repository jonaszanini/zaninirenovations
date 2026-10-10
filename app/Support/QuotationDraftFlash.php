<?php

namespace App\Support;

/**
 * Flash copy after saving a quotation editor.
 *
 * Draft updates must say the quote was saved as a draft — never only "updated (vN)".
 */
class QuotationDraftFlash
{
    public static function updated(string $typeLabel, object $quote, bool $linkedNow = false): string
    {
        if ((bool) ($quote->is_draft ?? false)) {
            return $typeLabel.' quotation saved as a draft.';
        }

        $number = trim((string) ($quote->quote_number ?? ''));
        if ($linkedNow) {
            return $typeLabel.' quotation '.$number.' updated and linked.';
        }

        return $typeLabel.' quotation '.$number.' updated (v'.(int) ($quote->version ?? 0).').';
    }
}
