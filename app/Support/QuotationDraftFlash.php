<?php

namespace App\Support;

/**
 * Flash copy after saving a quotation editor.
 *
 * Draft updates must say the quote was saved as a draft — never only "updated (vN)".
 */
class QuotationDraftFlash
{
    public static function updated(string $typeLabel, object $quote, bool $linkedNow, bool $wasDraftBeforeSave): string
    {
        if ((bool) ($quote->is_draft ?? false)) {
            $message = $typeLabel.' quotation saved as a draft.';
            if (! $wasDraftBeforeSave && self::clientLinkPaused($quote)) {
                $message .= ' The client\'s link is paused until you publish again.';
            }

            return $message;
        }

        $number = trim((string) ($quote->quote_number ?? ''));
        if ($linkedNow) {
            return $typeLabel.' quotation '.$number.' updated and linked.';
        }

        return $typeLabel.' quotation '.$number.' updated (v'.(int) ($quote->version ?? 0).').';
    }

    private static function clientLinkPaused(object $quote): bool
    {
        if (method_exists($quote, 'isLinked')) {
            return (bool) $quote->isLinked();
        }

        return (bool) ($quote->client_share_paused ?? false);
    }
}
