<?php

namespace App\Support;

/**
 * Optional rich-text note shown in the email that sends a quotation PDF to the client.
 * Shared by every quotation type (stored as client_email_message on each source quotation).
 */
class QuotationClientEmailMessage
{
    public const FIELD = 'client_email_message';

    public const MAX_LENGTH = 20000;

    /**
     * Sanitized HTML for storage, or null when the editor left only empty markup.
     */
    public static function normalize(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        if (! QuotationWorkDescriptionHtml::isHtml($html)) {
            $html = self::plainTextToHtml($html);
        }

        return QuotationWorkDescriptionHtml::normalizeForStorage(HtmlContentSanitizer::sanitize($html));
    }

    /**
     * HTML ready to drop into the client email body, or null when there is nothing to show.
     */
    public static function forEmail(?string $stored): ?string
    {
        $html = self::normalize($stored);
        if ($html === null) {
            return null;
        }

        $html = preg_replace('/<p(\s[^>]*)?>/i', '<p style="margin:0 0 12px 0;">', $html) ?? $html;
        $html = preg_replace('/<(ul|ol)(\s[^>]*)?>/i', '<$1 style="margin:0 0 12px 20px;padding:0;">', $html) ?? $html;
        $html = preg_replace('/<li(\s[^>]*)?>/i', '<li style="margin:4px 0;">', $html) ?? $html;

        return $html;
    }

    public static function plainTextToHtml(string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return '';
        }

        $blocks = preg_split('/\n{2,}/', $text) ?: [$text];

        return implode('', array_map(
            static fn (string $block): string => '<p>'.nl2br(e(trim($block)), false).'</p>',
            $blocks
        ));
    }
}
