<?php

namespace App\Services\SupplierWebsite;

class SupplierWebsiteDataExtractor
{
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
     *     website_url: ?string
     * }
     */
    public function extract(array $pages): array
    {
        $phones = [];
        $emails = [];
        $addresses = [];
        $social = [];
        $name = null;
        $hours = null;
        $logo = null;
        $description = null;
        $websiteUrl = $pages[0]['url'] ?? null;

        foreach ($pages as $page) {
            $html = $page['html'];
            $this->collectJsonLd($html, $phones, $emails, $addresses, $name, $hours, $logo, $description);
            $this->collectMeta($html, $name, $description, $logo);
            $this->collectTelMailto($html, $phones, $emails);
            $this->collectSocial($html, $social);
            $this->collectAddressRegex($html, $addresses);
        }

        return [
            'name' => $name,
            'phones' => $this->uniqueList($phones),
            'emails' => $this->uniqueList($emails),
            'addresses' => $this->uniqueList($addresses),
            'hours' => $hours,
            'social_links' => $this->uniqueList($social),
            'logo_url' => $logo,
            'description' => $description,
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
        array &$phones,
        array &$emails,
        array &$addresses,
        ?string &$name,
        ?string &$hours,
        ?string &$logo,
        ?string &$description
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
                $this->walkSchemaNode($node, $phones, $emails, $addresses, $name, $hours, $logo, $description);
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
        array &$phones,
        array &$emails,
        array &$addresses,
        ?string &$name,
        ?string &$hours,
        ?string &$logo,
        ?string &$description
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

        if ($logo === null) {
            if (isset($node['logo']) && is_string($node['logo'])) {
                $logo = trim($node['logo']);
            } elseif (isset($node['image']) && is_string($node['image'])) {
                $logo = trim($node['image']);
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

    private function collectMeta(string $html, ?string &$name, ?string &$description, ?string &$logo): void
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
            $logo = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
        }
        if ($name === null && preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            $name = trim(preg_replace('/\s*[|\-–—].*$/', '', $title) ?? $title);
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
    private function collectAddressRegex(string $html, array &$addresses): void
    {
        $plain = preg_replace('/\s+/', ' ', strip_tags($html)) ?? '';
        if (preg_match_all('/\d{1,6}\s+[A-Za-z0-9][\w\s\.\-\']{2,60},\s*[A-Za-z][\w\s\-]{2,40},\s*[A-Z]{2}\s*[A-Z0-9][A-Z0-9\s]{2,7}/', $plain, $matches)) {
            foreach ($matches[0] as $line) {
                $addresses[] = trim($line);
            }
        }
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
