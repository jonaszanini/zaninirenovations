<?php

namespace App\Support;

/**
 * Required Yes/No on every calculator quotation: ask the client to reply
 * "I accept" to the quotation email. Null means the admin has not chosen yet.
 */
class QuotationReplyToAccept
{
    public const FIELD = 'ask_reply_to_accept';

    public const MESSAGE = 'Choose Yes or No for “Ask client to reply I accept to this email” before saving or sending.';

    /** Route names that persist a calculator quotation (not previews, config, or AI). */
    private const SAVE_ROUTE_PATTERN = '/^admin\.quotations\.(standard|fence|paving|stairs|painting|flooring|tile|low-deck|basement|shed|millwork|trimming)\.(store|update)$/';

    public static function isQuotationSaveRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        return (bool) preg_match(self::SAVE_ROUTE_PATTERN, $routeName);
    }

    /**
     * @return bool|null  true = Yes, false = No, null = unset
     */
    public static function parse(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) {
                return true;
            }
            if ((int) $value === 0) {
                return false;
            }

            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    public static function toStorage(?bool $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }
}
