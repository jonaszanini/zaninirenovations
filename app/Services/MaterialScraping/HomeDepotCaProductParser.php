<?php

namespace App\Services\MaterialScraping;

use App\Services\OllamaService;
use Symfony\Component\DomCrawler\Crawler;

class HomeDepotCaProductParser
{
    private OllamaService $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    public function parse(string $html, string $pageUrl): ParsedProductData
    {
        $fromJsonLd = $this->parseJsonLd($html);
        if ($fromJsonLd->isComplete()) {
            return $fromJsonLd;
        }

        $fromDom = $this->parseDom($html);
        if ($fromDom->isComplete()) {
            return $fromDom;
        }

        $merged = $this->merge($fromJsonLd, $fromDom);
        if ($merged->isComplete()) {
            return $merged;
        }

        if ($this->ollama->isReachable()) {
            $snippet = $this->buildSnippetForAi($html);
            $ai = $this->ollama->extractProductFromHtmlSnippet($snippet, $pageUrl);

            return new ParsedProductData(
                name: filled($ai['name'] ?? null) ? (string) $ai['name'] : $merged->name,
                sku: filled($ai['sku'] ?? null) ? (string) $ai['sku'] : $merged->sku,
                priceCad: $this->normalizePrice($ai['price_cad'] ?? $merged->priceCad),
                imageUrl: filled($ai['image_url'] ?? null) ? (string) $ai['image_url'] : $merged->imageUrl,
                model: filled($ai['model'] ?? null) ? (string) $ai['model'] : $merged->model,
                source: 'ollama',
            );
        }

        return $merged;
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

    private function buildSnippetForAi(string $html): string
    {
        $crawler = new Crawler($html);

        foreach (['main', '[data-product-id]', '.product-details', 'body'] as $selector) {
            $nodes = $crawler->filter($selector);
            if ($nodes->count() > 0) {
                $snippet = $nodes->first()->html();
                if (strlen($snippet) > 500) {
                    return mb_substr($snippet, 0, 12000);
                }
            }
        }

        return mb_substr($html, 0, 12000);
    }
}
