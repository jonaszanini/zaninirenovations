<?php

namespace App\Support;

/**
 * Strips executable HTML from blog (and similar) content while keeping
 * the usual TinyMCE tags so published posts look the same.
 */
class HtmlContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'a', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'figure', 'figcaption',
        'blockquote', 'span', 'div', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'hr', 'sup', 'sub', 'pre', 'code', 'iframe',
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return $html;
        }

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $html = strip_tags($html, $allowed);

        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        $html = preg_replace_callback(
            '/\s(href|src)\s*=\s*(["\'])(.*?)\2/i',
            static function (array $m): string {
                $attr = strtolower($m[1]);
                $quote = $m[2];
                $value = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $trimmed = trim($value);
                $lower = strtolower($trimmed);

                if ($attr === 'href') {
                    if ($trimmed === '' || str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
                        return '';
                    }
                    if (preg_match('#^(https?:|mailto:|tel:|/|#)#i', $trimmed) !== 1) {
                        return '';
                    }
                }

                if ($attr === 'src') {
                    if (str_starts_with($lower, 'javascript:')) {
                        return '';
                    }
                    if (str_starts_with($lower, 'data:image/')) {
                        return ' src='.$quote.$value.$quote;
                    }
                    if (preg_match('#^(https?:|/)#i', $trimmed) !== 1) {
                        return '';
                    }
                }

                return ' '.$attr.'='.$quote.$value.$quote;
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<iframe\b([^>]*)>/i',
            static function (array $m): string {
                if (preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $m[1], $src) !== 1) {
                    return '';
                }
                $url = strtolower(html_entity_decode($src[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
                $ok = in_array($host, [
                    'www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com',
                    'player.vimeo.com', 'www.vimeo.com',
                ], true);
                if (! $ok) {
                    return '';
                }

                return '<iframe'.$m[1].'>';
            },
            $html
        ) ?? $html;

        return $html;
    }
}
