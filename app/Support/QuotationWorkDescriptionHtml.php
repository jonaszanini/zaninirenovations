<?php

namespace App\Support;

/**
 * Helpers for TinyMCE-backed quotation work_description fields.
 */
class QuotationWorkDescriptionHtml
{
    public static function isHtml(string $text): bool
    {
        $text = trim($text);

        return $text !== '' && $text !== strip_tags($text);
    }

    /**
     * Persist null when TinyMCE (or the user) left only empty markup.
     */
    public static function normalizeForStorage(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain === '' ? null : $html;
    }

    /**
     * Merge auto-generated plain-text scope lines with an optional work description
     * (plain or HTML) for the client PDF block.
     *
     * @param  list<string>  $autoLines
     */
    public static function combineAutoScopeWithWork(array $autoLines, ?string $workDescription): string
    {
        $auto = trim(implode("\n", $autoLines));
        $work = trim((string) $workDescription);

        if ($work === '') {
            return $auto;
        }

        if (! self::isHtml($work)) {
            return $auto === '' ? $work : $auto."\n\n".$work;
        }

        if ($auto === '') {
            return $work;
        }

        return '<p>'.nl2br(e($auto), false).'</p>'.$work;
    }
}
