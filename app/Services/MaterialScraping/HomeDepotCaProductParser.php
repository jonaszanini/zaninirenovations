<?php

namespace App\Services\MaterialScraping;

use Symfony\Component\DomCrawler\Crawler;

class HomeDepotCaProductParser
{
    private HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher;

    public function __construct(HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher)
    {
        $this->localizedPriceFetcher = $localizedPriceFetcher;
    }

    public function parse(string $html, string $pageUrl): ParsedProductData
    {
        $fromState = $this->parseHdcaState($html, $pageUrl);

        // hdca-state price is unlocalized; never treat it as authoritative when a store ID is set.
        if ($this->localizedPriceFetcher->configuredStoreId() !== null) {
            $fromState = new ParsedProductData(
                name: $fromState->name,
                sku: $fromState->sku,
                priceCad: null,
                imageUrl: $fromState->imageUrl,
                model: $fromState->model,
                source: $fromState->source,
            );
        } elseif ($fromState->isComplete()) {
            return $fromState;
        }

        $fromJsonLd = $this->parseJsonLd($html);
        if ($fromJsonLd->isComplete()) {
            return $fromJsonLd;
        }

        $fromMicrodata = $this->parseMicrodata($html);
        $fromDom = $this->parseDom($html);

        $merged = $this->merge($fromState, $this->merge($fromJsonLd, $this->merge($fromMicrodata, $fromDom)));
        if ($merged->isComplete()) {
            return $merged;
        }

        return $merged;
    }

    /**
     * Home Depot CA embeds SSR product data in <script id="hdca-state">.
     */
    private function parseHdcaState(string $html, string $pageUrl): ParsedProductData
    {
        if (! preg_match('/<script[^>]+id=["\']hdca-state["\'][^>]*>(.*?)<\/script>/is', $html, $match)) {
            return new ParsedProductData(source: 'hdca-state');
        }

        $state = json_decode(html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5), true);
        if (! is_array($state)) {
            return new ParsedProductData(source: 'hdca-state');
        }

        $product = $this->resolveHdcaProductNode($state, $pageUrl);
        if ($product === null) {
            return new ParsedProductData(source: 'hdca-state');
        }

        $price = null;
        if (isset($product['price']) && is_array($product['price'])) {
            $price = $this->normalizePrice($product['price']['value'] ?? $product['price']['formattedValue'] ?? null);
        }

        $image = null;
        if (isset($product['images'][0]['url'])) {
            $image = (string) $product['images'][0]['url'];
        }

        return new ParsedProductData(
            name: filled($product['name'] ?? null) ? (string) $product['name'] : null,
            sku: filled($product['code'] ?? null) ? (string) $product['code'] : null,
            priceCad: $price,
            imageUrl: $image,
            model: filled($product['modelNumber'] ?? null) ? (string) $product['modelNumber'] : null,
            source: 'hdca-state',
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private function resolveHdcaProductNode(array $state, string $pageUrl): ?array
    {
        $skuFromUrl = $this->skuFromUrl($pageUrl);
        if ($skuFromUrl !== null) {
            $directKey = 'product-'.$skuFromUrl;
            if (isset($state[$directKey])) {
                $node = $state[$directKey];
                $product = is_array($node['b'] ?? null) ? $node['b'] : (is_array($node) ? $node : null);
                if ($product !== null) {
                    return $product;
                }
            }
        }

        foreach ($state as $key => $node) {
            if (! is_array($node)) {
                continue;
            }

            $product = is_array($node['b'] ?? null) ? $node['b'] : $node;
            if (! is_array($product)) {
                continue;
            }

            if (! isset($product['price'], $product['name'])) {
                continue;
            }

            if ($skuFromUrl !== null && filled($product['code'] ?? null) && (string) $product['code'] !== $skuFromUrl) {
                continue;
            }

            if (is_string($key) && str_starts_with($key, 'product-')) {
                return $product;
            }

            if (isset($product['price']['value']) || isset($product['price']['formattedValue'])) {
                return $product;
            }
        }

        return null;
    }

    private function skuFromUrl(string $pageUrl): ?string
    {
        if (preg_match('~/product/(?:[^/]+/)?(\d{6,})~', $pageUrl, $match)) {
            return $match[1];
        }

        return null;
    }

    private function parseMicrodata(string $html): ParsedProductData
    {
        $name = null;
        $sku = null;
        $price = null;
        $image = null;

        if (preg_match('/hdca-product-schema-definition.*?itemprop=["\']name["\'][^>]*>([^<]+)/is', $html, $match)) {
            $name = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
        }

        if (preg_match('/itemprop=["\']sku["\'][^>]*>([^<]+)/i', $html, $match)) {
            $sku = trim($match[1]);
        }

        if (preg_match('/itemprop=["\']price["\'][^>]*content=["\']([^"\']+)/i', $html, $match)) {
            $price = $this->normalizePrice($match[1]);
        }

        if (preg_match('/itemprop=["\']image["\'][^>]*src=["\']([^"\']+)/i', $html, $match)) {
            $image = $match[1];
        }

        return new ParsedProductData($name, $sku, $price, $image, null, 'microdata');
    }

    private function parseJsonLd(string $html): ParsedProductData
    {
        $name = null;
        $sku = null;
        $price = null;
        $image = null;
        $model = null;

        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $rawJson) {
                $decoded = json_decode(html_entity_decode(trim($rawJson), ENT_QUOTES | ENT_HTML5), true);
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

                    $type = $node['@type'] ?? '';
                    $types = is_array($type) ? $type : [$type];
                    if (! in_array('Product', $types, true)) {
                        continue;
                    }

                    $name ??= filled($node['name'] ?? null) ? (string) $node['name'] : null;
                    $sku ??= filled($node['sku'] ?? null) ? (string) $node['sku'] : null;
                    $model ??= filled($node['mpn'] ?? null) ? (string) $node['mpn'] : null;

                    if (isset($node['image'])) {
                        $img = $node['image'];
                        $image ??= is_array($img) ? (string) ($img[0] ?? '') : (string) $img;
                    }

                    $offers = $node['offers'] ?? null;
                    if (is_array($offers)) {
                        if (isset($offers['price'])) {
                            $price ??= $this->normalizePrice($offers['price']);
                        } elseif (isset($offers[0]['price'])) {
                            $price ??= $this->normalizePrice($offers[0]['price']);
                        }
                    }
                }
            }
        }

        return new ParsedProductData($name, $sku, $price, $image ?: null, $model, 'json-ld');
    }

    private function parseDom(string $html): ParsedProductData
    {
        $crawler = new Crawler($html);

        $name = $this->firstText($crawler, [
            'h1.product-details__title',
            'h1[data-product-title]',
            'h1.hdca-product__description',
            'h1',
        ]);

        $priceRaw = $this->firstText($crawler, [
            '[data-price]',
            '.price-format__main-price',
            '.price__dollars',
            '.hdca-product__price-value',
            '.price',
        ]);

        $sku = $this->firstText($crawler, [
            '[data-product-id]',
            '.product-info-bar__detail--sku',
        ]);

        $image = null;
        foreach (['meta[property="og:image"]', 'meta[name="twitter:image"]'] as $sel) {
            $node = $crawler->filter($sel);
            if ($node->count() > 0) {
                $image = $node->attr('content');
                break;
            }
        }

        return new ParsedProductData(
            name: $name,
            sku: $sku,
            priceCad: $this->normalizePrice($priceRaw),
            imageUrl: $image,
            source: 'dom',
        );
    }

    private function firstText(Crawler $crawler, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $nodes = $crawler->filter($selector);
            if ($nodes->count() === 0) {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $nodes->first()->text()) ?? '');
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function normalizePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^\d.,]/', '', (string) $value) ?? '';
        $clean = str_replace(',', '', $clean);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 2);
    }

    private function merge(ParsedProductData $a, ParsedProductData $b): ParsedProductData
    {
        return new ParsedProductData(
            name: $a->name ?? $b->name,
            sku: $a->sku ?? $b->sku,
            priceCad: $a->priceCad ?? $b->priceCad,
            imageUrl: $a->imageUrl ?? $b->imageUrl,
            model: $a->model ?? $b->model,
            source: $a->source !== 'unknown' ? $a->source : $b->source,
        );
    }

}
