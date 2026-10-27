<?php

namespace App\Services\SupplierWebsite;

class SupplierWebsiteDataExtractor
{
    public const MAX_ADDRESSES = 5;
    /**
     * @param  list<array{url: string, html: string}>  $pages
     * @return array{
     *     name: ?string,
     *     phones: list<string>,
     *     emails: list<string>,
     *     addresses: list<string>,
     *     hours: ?string,
     *     social_links: list<string>,
     *     logo_url: ?string,
     *     description: ?string,
     *     tags: list<string>,
     *     website_url: ?string
     * }
     */
    public function extract(array $pages): array
    {
        $phones = [];
        $emails = [];
        $addresses = [];
        $social = [];
        $tagCandidates = [];
        $name = null;
        $hours = null;
        $logo = null;
        $description = null;
        $websiteUrl = $pages[0]['url'] ?? null;

        foreach ($pages as $page) {
            $html = $page['html'];
            $pageUrl = (string) ($page['url'] ?? '');
            $this->collectJsonLd($html, $pageUrl, $phones, $emails, $addresses, $name, $hours, $logo, $description, $tagCandidates);
            $this->collectMeta($html, $pageUrl, $name, $description, $logo, $tagCandidates);
            $this->collectTelMailto($html, $phones, $emails);
            $this->collectSocial($html, $social);
            $this->collectStructuredAddresses($html, $addresses);
            $this->collectAddressRegex($html, $addresses);
            $this->collectHeaderLogo($html, $pageUrl, $logo);
            $this->collectContentTags($html, $tagCandidates);
        }

        $addresses = array_slice($this->finalizeAddresses($addresses), 0, self::MAX_ADDRESSES);
        $baseUrl = is_string($websiteUrl) && $websiteUrl !== '' ? $websiteUrl : '';

        return [
            'name' => $name,
            'phones' => $this->uniqueList($phones),
            'emails' => $this->uniqueList($emails),
            'addresses' => $addresses,
            'hours' => $hours,
            'social_links' => $this->uniqueList($social),
            'logo_url' => $this->resolveHttpUrl($logo, $baseUrl),
            'description' => $description,
            'tags' => $this->finalizeTags($tagCandidates),
            'website_url' => $websiteUrl,
        ];
    }

    /**
     * @param  list<string>  $phones
     * @param  list<string>  $emails
     * @param  list<string>  $addresses
     */
    private function collectJsonLd(
        string $html,
        string $pageUrl,
        array &$phones,
        array &$emails,
        array &$addresses,
        ?string &$name,
        ?string &$hours,
        ?string &$logo,
        ?string &$description,
        array &$tagCandidates
    ): void {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            return;
        }

        foreach ($blocks[1] as $jsonText) {
            $decoded = json_decode(html_entity_decode(trim($jsonText), ENT_QUOTES | ENT_HTML5), true);
            if (! is_array($decoded)) {
                continue;
            }
            $nodes = isset($decoded['@graph']) && is_array($decoded['@graph'])
                ? $decoded['@graph']
                : [$decoded];
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $this->walkSchemaNode($node, $pageUrl, $phones, $emails, $addresses, $name, $hours, $logo, $description, $tagCandidates);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $phones
     * @param  list<string>  $emails
     * @param  list<string>  $addresses
     */
    private function walkSchemaNode(
        array $node,
        string $pageUrl,
        array &$phones,
        array &$emails,
        array &$addresses,
        ?string &$name,
        ?string &$hours,
        ?string &$logo,
        ?string &$description,
        array &$tagCandidates
    ): void {
        $type = $this->schemaType($node);
        $isBusiness = $type !== null && (
            str_contains($type, 'LocalBusiness')
            || str_contains($type, 'Store')
            || str_contains($type, 'Organization')
        );

        if ($isBusiness && $name === null && isset($node['name']) && is_string($node['name'])) {
            $name = trim($node['name']);
        }

        if ($isBusiness && $description === null && isset($node['description']) && is_string($node['description'])) {
            $description = trim($node['description']);
        }

        if (isset($node['telephone']) && is_string($node['telephone'])) {
            $phones[] = $this->normalizePhoneDigits($node['telephone']);
        }

        if (isset($node['email']) && is_string($node['email'])) {
            $emails[] = strtolower(trim($node['email']));
        }

        if (isset($node['address'])) {
            $rawAddress = $node['address'];
            if (is_array($rawAddress) && array_is_list($rawAddress)) {
                foreach ($rawAddress as $item) {
                    $formatted = $this->formatPostalAddress($item);
                    if ($formatted !== null) {
                        $addresses[] = $formatted;
                    }
                }
            } else {
                $formatted = $this->formatPostalAddress($rawAddress);
                if ($formatted !== null) {
                    $addresses[] = $formatted;
                }
            }
        }

        if ($hours === null && isset($node['openingHoursSpecification'])) {
            $hours = $this->formatOpeningHours($node['openingHoursSpecification']);
        }
        if ($hours === null && isset($node['openingHours']) && is_string($node['openingHours'])) {
            $hours = trim($node['openingHours']);
        }

        if (isset($node['logo']) && is_string($node['logo'])) {
            $this->assignLogo($logo, $node['logo'], $pageUrl);
        } elseif (isset($node['logo']) && is_array($node['logo']) && isset($node['logo']['url']) && is_string($node['logo']['url'])) {
            $this->assignLogo($logo, $node['logo']['url'], $pageUrl);
        } elseif ($logo === null && isset($node['image']) && is_string($node['image'])) {
            $this->assignLogo($logo, $node['image'], $pageUrl);
        }

        foreach (['serviceType', 'knowsAbout'] as $tagKey) {
            if (! isset($node[$tagKey])) {
                continue;
            }
            $raw = $node[$tagKey];
            if (is_string($raw)) {
                $tagCandidates[] = $this->normalizeTagCandidate($raw);
            } elseif (is_array($raw)) {
                foreach ($raw as $item) {
                    if (is_string($item)) {
                        $tagCandidates[] = $this->normalizeTagCandidate($item);
                    }
                }
            }
        }

        if (isset($node['hasOfferCatalog']['itemListElement']) && is_array($node['hasOfferCatalog']['itemListElement'])) {
            foreach ($node['hasOfferCatalog']['itemListElement'] as $item) {
                if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                    $tagCandidates[] = $item['name'];
                }
            }
        }
    }

    /** @param  array<string, mixed>  $node */
    private function schemaType(array $node): ?string
    {
        $type = $node['@type'] ?? null;
        if (is_string($type)) {
            return $type;
        }
        if (is_array($type)) {
            return implode(',', array_map('strval', $type));
        }

        return null;
    }

    /** @param  mixed  $address */
    private function formatPostalAddress($address): ?string
    {
        if (is_string($address)) {
            $trimmed = trim($address);

            return $trimmed !== '' ? $trimmed : null;
        }
        if (! is_array($address)) {
            return null;
        }

        $parts = [];
        foreach (['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'] as $key) {
            if (! empty($address[$key]) && is_string($address[$key])) {
                $parts[] = trim($address[$key]);
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    /** @param  mixed  $spec */
    private function formatOpeningHours($spec): ?string
    {
        if (is_string($spec)) {
            return trim($spec) !== '' ? trim($spec) : null;
        }
        if (! is_array($spec)) {
            return null;
        }

        $lines = [];
        $items = isset($spec['dayOfWeek']) ? [$spec] : $spec;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $days = $item['dayOfWeek'] ?? '';
            if (is_array($days)) {
                $days = implode(', ', $days);
            }
            $opens = (string) ($item['opens'] ?? '');
            $closes = (string) ($item['closes'] ?? '');
            if ($days !== '' && ($opens !== '' || $closes !== '')) {
                $lines[] = trim($days.' '.$opens.'–'.$closes);
            }
        }

        return $lines !== [] ? implode('; ', $lines) : null;
    }

    private function collectMeta(string $html, string $pageUrl, ?string &$name, ?string &$description, ?string &$logo, array &$tagCandidates): void
    {
        if ($name === null && preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $name = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
        }
        if ($description === null && preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $description = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
        }
        if ($description === null && preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $description = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
        }
        if ($logo === null && preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $this->assignLogo($logo, $m[1], $pageUrl);
        }
        if ($name === null && preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            $name = trim(preg_replace('/\s*[|\-–—].*$/', '', $title) ?? $title);
        }

        if (preg_match('/<meta[^>]+name=["\']keywords["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            foreach (preg_split('/\s*,\s*/', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5)) ?: [] as $kw) {
                $tagCandidates[] = trim($kw);
            }
        }
    }

    /** @param  list<string>  $phones @param  list<string>  $emails */
    private function collectTelMailto(string $html, array &$phones, array &$emails): void
    {
        if (preg_match_all('/href=["\']tel:([^"\']+)/i', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $phones[] = $this->normalizePhoneDigits(urldecode($raw));
            }
        }
        if (preg_match_all('/href=["\']mailto:([^"\'?]+)/i', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $emails[] = strtolower(trim(urldecode($raw)));
            }
        }

        if (preg_match_all('/\b(?:\+?1[\s.\-]?)?(?:\(?\d{3}\)?[\s.\-]?)\d{3}[\s.\-]?\d{4}\b/', $html, $phoneMatches)) {
            foreach ($phoneMatches[0] as $raw) {
                $digits = $this->normalizePhoneDigits($raw);
                if (strlen($digits) === 10) {
                    $phones[] = $digits;
                }
            }
        }

        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $html, $emailMatches)) {
            foreach ($emailMatches[0] as $raw) {
                if (! str_contains(strtolower($raw), 'example.com')) {
                    $emails[] = strtolower($raw);
                }
            }
        }
    }

    /** @param  list<string>  $social */
    private function collectSocial(string $html, array &$social): void
    {
        if (! preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches)) {
            return;
        }
        foreach ($matches[1] as $url) {
            $lower = strtolower($url);
            if (str_contains($lower, 'facebook.com/')
                || str_contains($lower, 'instagram.com/')
                || str_contains($lower, 'linkedin.com/')
                || str_contains($lower, 'twitter.com/')
                || str_contains($lower, 'x.com/')
                || str_contains($lower, 'youtube.com/')
                || str_contains($lower, 'tiktok.com/')) {
                $social[] = $url;
            }
        }
    }

    /** @param  list<string>  $addresses */
    private function collectStructuredAddresses(string $html, array &$addresses): void
    {
        if (preg_match_all('/<address\b[^>]*>(.*?)<\/address>/is', $html, $blocks)) {
            foreach ($blocks[1] as $inner) {
                $text = $this->collapseWhitespace(strip_tags($inner));
                if ($text !== '') {
                    $addresses[] = $text;
                }
            }
        }

        if (preg_match_all(
            '/<(?:p|div|li|span|td|th|h[1-6])\b[^>]*>(?:(?!<\/(?:p|div|li|span|td|th|h[1-6])>).)*<a\s[^>]*href\s*=\s*["\'][^"\']*google\.[^"\']*\/maps[^"\']*["\'][^>]*>(.*?)<\/a>(?:(?!<\/(?:p|div|li|span|td|th|h[1-6])>).)*<\/(?:p|div|li|span|td|th|h[1-6])>/is',
            $html,
            $containers
        )) {
            foreach ($containers[0] as $block) {
                if (! preg_match_all('/<a\s[^>]*href\s*=\s*["\'][^"\']*google\.[^"\']*\/maps[^"\']*["\'][^>]*>(.*?)<\/a>/is', $block, $linkMatches)) {
                    continue;
                }
                $parts = [];
                foreach ($linkMatches[1] as $anchorHtml) {
                    $part = $this->collapseWhitespace(strip_tags($anchorHtml));
                    if ($part !== '' && ! $this->isMapsLinkLabelNoise($part)) {
                        $parts[] = $part;
                    }
                }
                if ($parts !== []) {
                    $addresses[] = $this->joinAddressParts($parts);
                }
            }
        } elseif (preg_match_all('/<a\s[^>]*href\s*=\s*["\'][^"\']*google\.[^"\']*\/maps[^"\']*["\'][^>]*>(.*?)<\/a>/is', $html, $mapLinks)) {
            $parts = [];
            foreach ($mapLinks[1] as $anchorHtml) {
                $part = $this->collapseWhitespace(strip_tags($anchorHtml));
                if ($part !== '' && ! $this->isMapsLinkLabelNoise($part)) {
                    $parts[] = $part;
                }
            }
            if ($parts !== []) {
                $addresses[] = $this->joinAddressParts($parts);
            }
        }

        if (preg_match_all('/\bdata-address\s*=\s*["\']([^"\']+)["\']/i', $html, $dataAttrs)) {
            foreach ($dataAttrs[1] as $raw) {
                $text = $this->collapseWhitespace(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
                if ($text !== '') {
                    $addresses[] = $text;
                }
            }
        }

        $this->collectCanadianPostalAddresses($html, $addresses);
    }

    /** @param  list<string>  $addresses */
    private function collectCanadianPostalAddresses(string $html, array &$addresses): void
    {
        $plain = $this->collapseWhitespace(strip_tags($html));
        if (preg_match_all(
            '/(\d{1,6}[\w\s\.\-\',#]{3,80}?)\s*,?\s*([A-Za-z][\w\s\-]{2,40})\s*,?\s*(ON|QC|BC|AB|MB|SK|NS|NB|NL|PE|YT|NT|NU)\s+([A-Z]\d[A-Z])\s*(\d[A-Z]\d)/i',
            $plain,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $street = trim($m[1], " \t\n\r\0\x0B,");
                if ($this->looksLikePhoneOrOrderFragment($street)) {
                    continue;
                }
                $city = trim($m[2]);
                $region = strtoupper($m[3]);
                $postal = strtoupper($m[4].' '.$m[5]);
                $addresses[] = $street.', '.$city.', '.$region.' '.$postal;
            }
        } elseif (preg_match_all(
            '/([A-Za-z0-9][\w\s\.\-\',#]{3,80}?)\s+([A-Z]\d[A-Z])\s*(\d[A-Z]\d)/i',
            $plain,
            $shortMatches,
            PREG_SET_ORDER
        )) {
            foreach ($shortMatches as $m) {
                $line = trim($m[1]);
                $postal = strtoupper($m[2].' '.$m[3]);
                if (preg_match('/\d/', $line)) {
                    $addresses[] = $line.' '.$postal;
                }
            }
        }
    }

    /** @param  list<string>  $addresses */
    private function collectAddressRegex(string $html, array &$addresses): void
    {
        $plain = $this->collapseWhitespace(strip_tags($html));
        if (preg_match_all('/\d{1,6}\s+[A-Za-z0-9][\w\s\.\-\']{2,60},\s*[A-Za-z][\w\s\-]{2,40},\s*[A-Z]{2}\s*[A-Z0-9][A-Z0-9\s]{2,7}/', $plain, $matches)) {
            foreach ($matches[0] as $line) {
                $addresses[] = trim($line);
            }
        }
    }

    private function collectHeaderLogo(string $html, string $pageUrl, ?string &$logo): void
    {
        if ($logo !== null) {
            return;
        }
        if (! preg_match_all('/<img\b[^>]*>/i', $html, $tags)) {
            return;
        }
        foreach ($tags[0] as $tag) {
            $blob = strtolower($tag);
            if (! str_contains($blob, 'logo')) {
                continue;
            }
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $m)) {
                $this->assignLogo($logo, $m[1], $pageUrl);
                return;
            }
        }
    }

    /** @param  list<string>  $tagCandidates */
    private function collectContentTags(string $html, array &$tagCandidates): void
    {
        if (preg_match_all('/<(?:nav|header)\b[^>]*>(.*?)<\/(?:nav|header)>/is', $html, $navBlocks)) {
            foreach ($navBlocks[1] as $block) {
                if (preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $block, $links)) {
                    foreach ($links[1] as $label) {
                        $text = $this->collapseWhitespace(strip_tags($label));
                        if ($text !== '') {
                            $tagCandidates[] = $text;
                        }
                    }
                }
            }
        }

        if (preg_match_all('/<h[1-3]\b[^>]*>(.*?)<\/h[1-3]>/is', $html, $headings)) {
            foreach ($headings[1] as $heading) {
                $text = $this->collapseWhitespace(strip_tags($heading));
                if ($text !== '') {
                    $tagCandidates[] = $text;
                }
            }
        }
    }

    /** @param  list<string>  $parts */
    private function joinAddressParts(array $parts): string
    {
        $joined = implode(', ', $parts);
        $joined = preg_replace('/,\s*([A-Z]\d[A-Z]\s*\d[A-Z]\d)/i', ' $1', $joined) ?? $joined;

        return trim($joined);
    }

    private function collapseWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** @param  list<string>  $tagCandidates @return list<string> */
    private function finalizeTags(array $tagCandidates): array
    {
        $stop = [
            'home', 'about', 'about us', 'contact', 'contact us', 'blog', 'news', 'menu', 'search',
            'read more', 'learn more', 'get a quote', 'quote', 'privacy', 'terms', 'faq', 'faqs',
            'facebook', 'instagram', 'linkedin', 'twitter', 'login', 'cart', 'shop now', 'shop',
            'click here', 'sign up', 'subscribe', 'view all', 'see all',
        ];
        $out = [];
        foreach ($tagCandidates as $raw) {
            foreach (preg_split('/\s*[|,;\/]\s*/', trim($raw)) ?: [] as $piece) {
                $piece = trim($piece);
                if ($piece === '' || mb_strlen($piece) < 3 || mb_strlen($piece) > 48) {
                    continue;
                }
                if (preg_match('/^https?:\/\//i', $piece)) {
                    continue;
                }
                $lower = mb_strtolower($piece);
                if (in_array($lower, $stop, true)) {
                    continue;
                }
                if (preg_match('/^welcome to\b/u', $lower)) {
                    continue;
                }
                if (preg_match('/\b(shop now|click here|contact us|read more)\b/u', $lower)) {
                    continue;
                }
                if (preg_match('/^\d+$/', $piece)) {
                    continue;
                }
                $key = $lower;
                if (! isset($out[$key])) {
                    $out[$key] = $this->titleCaseTag($piece);
                }
            }
            if (count($out) >= 6) {
                break;
            }
        }

        return array_slice(array_values($out), 0, 6);
    }

    private function titleCaseTag(string $text): string
    {
        if (str_contains($text, '-')) {
            return $text;
        }

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    private function assignLogo(?string &$logo, string $candidate, string $pageUrl): void
    {
        if ($logo !== null) {
            return;
        }
        $resolved = $this->resolveHttpUrl($candidate, $pageUrl);
        if ($resolved !== null) {
            $logo = $resolved;
        }
    }

    private function normalizeTagCandidate(string $raw): string
    {
        return $this->collapseWhitespace(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)));
    }

    public function resolveHttpUrl(?string $raw, string $baseUrl): ?string
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
        if ($raw === '') {
            return null;
        }
        $lower = strtolower($raw);
        if (str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'blob:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $this->normalizeHttpUrl($raw);
        }
        if ($baseUrl === '') {
            return null;
        }
        if (str_starts_with($raw, '//')) {
            $parts = parse_url($baseUrl);
            $scheme = (string) ($parts['scheme'] ?? 'https');

            return $this->normalizeHttpUrl($scheme.':'.$raw);
        }
        $parts = parse_url($baseUrl);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        if (str_starts_with($raw, '/')) {
            return $this->normalizeHttpUrl($scheme.'://'.$host.$port.$raw);
        }
        $basePath = (string) ($parts['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        if ($dir === '' || $dir === '.') {
            $dir = '';
        }

        return $this->normalizeHttpUrl($scheme.'://'.$host.$port.$dir.'/'.$raw);
    }

    private function normalizeHttpUrl(string $url): ?string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }

    private function isMapsLinkLabelNoise(string $part): bool
    {
        $lower = mb_strtolower(trim($part));

        return in_array($lower, [
            'directions',
            'get directions',
            'view map',
            'view on map',
            'map',
            'open in maps',
            'google maps',
            'see map',
        ], true);
    }

    private function looksLikePhoneOrOrderFragment(string $text): bool
    {
        if (preg_match('/\b(?:\+?1[\s.\-]?)?(?:\(?\d{3}\)?[\s.\-]?)\d{3}[\s.\-]?\d{4}\b/', $text)) {
            return true;
        }
        if (preg_match('/\border\s*#?\s*\d+/i', $text)) {
            return true;
        }
        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) >= 10 && ! preg_match('/\b(?:st|street|ave|avenue|rd|road|blvd|drive|dr|unit|suite|industrial)\b/i', $text)) {
            return true;
        }

        return false;
    }

    /** @param  list<string>  $addresses @return list<string> */
    private function finalizeAddresses(array $addresses): array
    {
        $candidates = [];
        foreach ($addresses as $addr) {
            $addr = trim($addr);
            if ($addr === '' || $this->isSpuriousAddress($addr)) {
                continue;
            }
            $candidates[] = $addr;
        }

        usort($candidates, fn (string $a, string $b) => $this->addressScore($b) <=> $this->addressScore($a));

        $unique = [];
        foreach ($candidates as $addr) {
            $key = $this->normalizeAddressKey($addr);
            if (! isset($unique[$key])) {
                $unique[$key] = $addr;
            }
        }

        $list = array_values($unique);
        $result = [];
        foreach ($list as $addr) {
            $norm = $this->normalizeAddressKey($addr);
            $drop = false;
            foreach ($list as $other) {
                if ($addr === $other) {
                    continue;
                }
                $otherNorm = $this->normalizeAddressKey($other);
                if ($norm !== $otherNorm && str_contains($otherNorm, $norm)) {
                    $drop = true;
                    break;
                }
            }
            if (! $drop) {
                $result[] = $addr;
            }
        }

        return $result;
    }

    private function isSpuriousAddress(string $addr): bool
    {
        if ($this->isMapsLinkLabelNoise($addr)) {
            return true;
        }
        if ($this->looksLikePhoneOrOrderFragment($addr)) {
            return true;
        }

        return false;
    }

    private function addressScore(string $addr): int
    {
        $score = strlen($addr);
        if (preg_match('/\b[A-Z]\d[A-Z]\s*\d[A-Z]\d\b/i', $addr)) {
            $score += 200;
        }
        if (preg_match('/\b(?:ON|QC|BC|AB|MB|SK|NS|NB|NL|PE|YT|NT|NU)\b/i', $addr)) {
            $score += 50;
        }

        return $score;
    }

    private function normalizeAddressKey(string $addr): string
    {
        $key = mb_strtolower($addr);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;
        $key = preg_replace('/[.,]/', '', $key) ?? $key;

        return trim($key);
    }

    private function normalizePhoneDigits(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /** @param  list<string>  $items @return list<string> */
    private function uniqueList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $key = mb_strtolower($item);
            if (! isset($out[$key])) {
                $out[$key] = $item;
            }
        }

        return array_values($out);
    }
}
