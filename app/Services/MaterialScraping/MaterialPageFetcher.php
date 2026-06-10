<?php

namespace App\Services\MaterialScraping;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches retailer HTML. Prefer Browsershot locally for JS-heavy pages.
 */
class MaterialPageFetcher
{
    public function fetch(string $url): string
    {
        $fetcher = (string) config('materials.scraper.fetcher', 'http');

        if ($fetcher === 'browsershot' && class_exists(\Spatie\Browsershot\Browsershot::class)) {
            return $this->fetchViaBrowsershot($url);
        }

        return $this->fetchViaHttp($url);
    }

    private function fetchViaHttp(string $url): string
    {
        $timeout = max(10, (int) config('materials.scraper.timeout_seconds', 60));

        $response = Http::withHeaders([
            'User-Agent' => (string) config('materials.scraper.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-CA,en;q=0.9',
        ])
            ->timeout($timeout)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' fetching '.$url);
        }

        $body = (string) $response->body();
        if (strlen(trim($body)) < 200) {
            throw new RuntimeException('Empty or very short HTML response — try MATERIALS_SCRAPER_FETCHER=browsershot locally.');
        }

        return $body;
    }

    private function fetchViaBrowsershot(string $url): string
    {
        $timeout = max(10, (int) config('materials.scraper.timeout_seconds', 60));
        $delayMs = max(1000, (int) config('materials.scraper.delay_after_load_ms', 5000));

        // Home Depot never reaches networkidle0 (analytics/ads). Load DOM, wait for
        // product markup, then pause for price JS. setDelay() breaks on Puppeteer 24+
        // (waitForTimeout removed), so we use waitForFunction instead.
        /** @var \Spatie\Browsershot\Browsershot $shot */
        $shot = \Spatie\Browsershot\Browsershot::url($url)
            ->setNodeModulePath(base_path())
            ->userAgent((string) config('materials.scraper.user_agent'))
            ->timeout($timeout)
            ->setOption('waitUntil', 'domcontentloaded')
            ->waitForSelector('h1', ['timeout' => min(30, $timeout) * 1000])
            ->waitForFunction(
                '() => new Promise(resolve => setTimeout(resolve, '.(int) $delayMs.'))',
                \Spatie\Browsershot\Browsershot::POLLING_MUTATION,
                $delayMs + 10000
            );

        $html = $shot->bodyHtml();

        if (strlen(trim($html)) < 200) {
            throw new RuntimeException('Browsershot returned empty HTML for '.$url);
        }

        return $html;
    }
}
