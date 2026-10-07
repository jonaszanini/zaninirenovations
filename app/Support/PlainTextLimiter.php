<?php

namespace App\Support;

/**
 * Trim plain text to a character budget at a sentence or word boundary.
 * Never cuts mid-word or leaves a trailing comma.
 */
class PlainTextLimiter
{
    public static function trimToLength(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '' || $max < 1) {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return self::stripTrailingJunk($text);
        }

        $window = mb_substr($text, 0, $max);

        if (preg_match_all('/.*?[.!?](?=\s|$)/u', $window, $matches) > 0) {
            $sentence = trim(implode('', $matches[0]));
            if (mb_strlen($sentence) >= 20) {
                return self::stripTrailingJunk($sentence);
            }
        }

        if (preg_match('/^(.*)\s+\S*$/u', $window, $wordMatch) === 1) {
            $words = self::stripTrailingJunk($wordMatch[1]);
            if ($words !== '') {
                return $words;
            }
        }

        return self::stripTrailingJunk($window);
    }

    private static function stripTrailingJunk(string $text): string
    {
        return rtrim($text, " \t\n\r\0\x0B,;:—–-");
    }
}
