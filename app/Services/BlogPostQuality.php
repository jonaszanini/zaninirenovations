<?php

namespace App\Services;

class BlogPostQuality
{
    /**
     * @return list<array{code: string, severity: string, message: string}>
     */
    public static function contentIssues(string $html): array
    {
        $issues = [];
        $plain = self::plainText($html);

        if (preg_match('/\blicensed\b/i', $plain) === 1 || preg_match('/\blicensed\b/i', $html) === 1) {
            $issues[] = [
                'code' => 'licensed',
                'severity' => 'error',
                'message' => 'The word “licensed” appears. The business is not licensed — remove or rephrase it.',
            ];
        }

        if (preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
                    $issues[] = [
                        'code' => 'empty_heading',
                        'severity' => 'error',
                        'message' => 'An empty heading was found. Add text or remove the heading.',
                    ];
                    break;
                }
            }
        }

        if (! self::hasInternalLink($html)) {
            $issues[] = [
                'code' => 'internal_links',
                'severity' => 'warning',
                'message' => 'No internal links to zaninirenovations.ca (or a site path) were found.',
            ];
        }

        if (self::imagesMissingAlt($html) > 0) {
            $issues[] = [
                'code' => 'missing_alt',
                'severity' => 'warning',
                'message' => 'One or more inline images are missing alt text.',
            ];
        }

        return $issues;
    }

    /**
     * @param  array{
     *   title?: string,
     *   slug?: string,
     *   html_content?: string,
     *   excerpt?: string,
     *   seo_title?: string,
     *   meta_description?: string,
     *   featured_image?: bool,
     *   featured_image_alt?: string,
     *   category_ids?: list<int|string>,
     * }  $fields
     * @return array{ready: bool, items: list<array{key: string, label: string, ok: bool, blocking: bool, detail?: string}>}
     */
    public static function checklist(array $fields): array
    {
        $html = (string) ($fields['html_content'] ?? '');
        $plain = self::plainText($html);
        $words = self::wordCount($plain);
        $issues = self::contentIssues($html);
        $issueCodes = array_column($issues, 'code');
        $hasFeatured = (bool) ($fields['featured_image'] ?? false);
        $featuredAlt = trim((string) ($fields['featured_image_alt'] ?? ''));
        $categoryIds = array_values(array_filter($fields['category_ids'] ?? [], fn ($id) => (string) $id !== ''));

        $items = [
            self::item('title', 'Title', trim((string) ($fields['title'] ?? '')) !== '', true),
            self::item('slug', 'Slug', trim((string) ($fields['slug'] ?? '')) !== '', true),
            self::item(
                'content',
                'Body copy',
                $words >= 80,
                true,
                $words > 0 ? $words.' words' : 'Add article content'
            ),
            self::item('licensed', 'No “licensed” wording', ! in_array('licensed', $issueCodes, true), true),
            self::item('empty_heading', 'Headings have text', ! in_array('empty_heading', $issueCodes, true), true),
            self::item('featured', 'Featured image', $hasFeatured, false),
            self::item('featured_alt', 'Featured image alt', ! $hasFeatured || $featuredAlt !== '', false),
            self::item('seo_title', 'SEO title', trim((string) ($fields['seo_title'] ?? '')) !== '', false),
            self::item('meta', 'Meta description', trim((string) ($fields['meta_description'] ?? '')) !== '', false),
            self::item('excerpt', 'Excerpt', trim((string) ($fields['excerpt'] ?? '')) !== '', false),
            self::item('category', 'Category', $categoryIds !== [], false),
            self::item('internal_links', 'Internal link', ! in_array('internal_links', $issueCodes, true), false),
            self::item('inline_alt', 'Inline image alt text', ! in_array('missing_alt', $issueCodes, true), false),
        ];

        $ready = true;
        foreach ($items as $item) {
            if ($item['blocking'] && ! $item['ok']) {
                $ready = false;
                break;
            }
        }

        return [
            'ready' => $ready,
            'items' => $items,
            'issues' => $issues,
        ];
    }

    public static function imagesMissingAlt(string $html): int
    {
        if (! preg_match_all('/<img\b[^>]*>/i', $html, $matches)) {
            return 0;
        }

        $missing = 0;
        foreach ($matches[0] as $tag) {
            if (! preg_match('/\balt\s*=\s*([\'"])(.*?)\1/i', $tag, $alt)) {
                $missing++;
                continue;
            }
            if (trim(html_entity_decode($alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
                $missing++;
            }
        }

        return $missing;
    }

    public static function hasInternalLink(string $html): bool
    {
        if (! preg_match_all('/<a\b[^>]*href\s*=\s*([\'"])(.*?)\1/i', $html, $matches)) {
            return false;
        }

        foreach ($matches[2] as $href) {
            $href = trim($href);
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }
            if (str_starts_with($href, '/')) {
                return true;
            }
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if ($host === 'zaninirenovations.ca' || $host === 'www.zaninirenovations.ca') {
                return true;
            }
        }

        return false;
    }

    public static function wordCount(string $plain): int
    {
        $plain = trim($plain);
        if ($plain === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    public static function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array{key: string, label: string, ok: bool, blocking: bool, detail?: string}
     */
    private static function item(string $key, string $label, bool $ok, bool $blocking, ?string $detail = null): array
    {
        $item = [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'blocking' => $blocking,
        ];
        if ($detail !== null) {
            $item['detail'] = $detail;
        }

        return $item;
    }
}
