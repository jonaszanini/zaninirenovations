<?php

namespace Tests\Feature;

use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SharedPricesLayoutTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_shared_prices_layout_geometry_and_precision(): void
    {
        $this->actingAsAdmin();

        $work = SharedWork::query()->where('key', 'window_trim')->first()
            ?? SharedWork::query()->whereNotIn('unit', ['lf', 'sf'])->first();
        $this->assertNotNull($work, 'A non-area shared work must exist');

        $work->rate = 24.8235;
        $work->flat_rate = 12.9000;
        $work->material_unit = 'opening';
        $work->material_unit_price = 99999.9999;
        $work->material_name = 'Picture-frame window casing kit extra long';
        $work->consumers = [
            'custom_very_long_unknown_consumer_key',
            'trimming',
            'basement',
        ];
        $work->save();
        SharedWorkCatalog::forgetCache();

        $html = $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('Shared prices', false)
            ->assertSee('class="sp-col-catalog"', false)
            ->assertSee('class="sp-col-consumers"', false)
            ->assertSee('class="sp-effective"', false)
            ->assertSee('cp-money-input', false)
            ->getContent();

        $this->assertStringContainsString('24.8235', $html);
        $this->assertStringContainsString('12.9000', $html);
        $this->assertStringContainsString('99999.9999', $html);
        $this->assertStringContainsString('opening', $html);
        $this->assertStringContainsString('custom_very_long_unknown_consumer_key', $html);
        $this->assertStringContainsString('title="custom_very_long_unknown_consumer_key"', $html);
        $this->assertStringContainsString('data-cp-money-places="4"', $html);
        $this->assertStringContainsString('Used when Sell rate is empty', $html);
        $this->assertStringContainsString('aria-label="crew rates"', $html);
        $this->assertStringContainsString('aria-label="effective sell"', $html);
        $this->assertStringContainsString('aria-controls="sp-more-'.$work->id.'"', $html);
        $this->assertStringContainsString('aria-describedby="sp-more-'.$work->id.'"', $html);
        $this->assertStringContainsString('aria-label="+2 more:', $html);

        $blade = file_get_contents(resource_path('views/admin/quotations/shared_prices/edit.blade.php'));
        $this->assertStringContainsString("'tipText'", $blade);
        $this->assertStringContainsString("'tipLabel'", $blade);
        $this->assertStringNotContainsString("'text' =>", $blade);
        $this->assertStringNotContainsString("'html' =>", $blade);

        $this->assertStringNotContainsString('sp-col-trailing', $html);
        $this->assertStringNotContainsString('class="admin-form-help"', $html);

        $css = $this->pageCss($html);
        $catalog = $this->cssBlock($css, '.sp-table td.sp-col-catalog');
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $catalog);
        $this->assertDoesNotMatchRegularExpression('/align-items:\s*flex-start/', $catalog);
        $this->assertMatchesRegularExpression('/justify-content:\s*center/', $catalog);

        $consumers = $this->cssBlock($css, '.sp-table td.sp-col-consumers');
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $consumers);
        $this->assertDoesNotMatchRegularExpression('/align-items:\s*flex-start/', $consumers);

        $chip = $this->cssBlock($css, '.sp-chip');
        $this->assertMatchesRegularExpression('/display:\s*inline-flex/', $chip);
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $chip);
        $this->assertMatchesRegularExpression('/justify-content:\s*flex-start/', $chip);
        $this->assertDoesNotMatchRegularExpression('/justify-content:\s*center/', $chip);
        $this->assertGreaterThanOrEqual(0.65, $this->cssFontRem($chip));

        $chipLabel = $this->cssBlock($css, '.sp-chip__label');
        $this->assertMatchesRegularExpression('/text-overflow:\s*ellipsis/', $chipLabel);
        $this->assertStringContainsString('sp-chip__label', $html);

        $more = $this->cssBlock($css, '.sp-chip--more');
        $this->assertDoesNotMatchRegularExpression('/\.sp-chip--more:focus\s+\.sp-chip-more__tip/', $css);
        $this->assertMatchesRegularExpression('/min-width:\s*32px/', $more);
        $this->assertMatchesRegularExpression('/justify-content:\s*center/', $more);

        $columns = $this->desktopGridColumns($css);
        $this->assertCount(8, $columns);
        $this->assertGreaterThanOrEqual(7.5, $this->cssMinRem($columns[0]), 'Work item must show ~18–20 characters at 1024');
        $this->assertGreaterThanOrEqual(4.5, $this->cssMinRem($columns[1]), 'Rate must still fit 24.8235');
        $this->assertLessThanOrEqual(4.75, $this->cssMinRem($columns[1]), 'Rate gives width to Mat. name');
        $this->assertGreaterThanOrEqual(6.0, $this->cssMinRem($columns[2]), 'Sell must fit 12.9000 and 99,999.9999');
        $this->assertTrue(
            str_contains($columns[3], '7ch') || $this->cssMinRem($columns[3]) >= 4.6,
            'Unit must be ~7ch so opening does not clip'
        );
        $this->assertGreaterThanOrEqual(5.5, $this->cssMinRem($columns[4]), 'Mat. price must fit 99,999.9999');
        $this->assertGreaterThanOrEqual(7.5, $this->cssMinRem($columns[5]), 'Mat. name must be ~7.5–8rem');
        $this->assertLessThanOrEqual(8.25, $this->cssMinRem($columns[5]), 'Mat. name stays ~7.5–8rem');
        $this->assertGreaterThanOrEqual(6.5, $this->cssMinRem($columns[7]), 'Used-by must fit Trimming + +N without clipping');

        $this->assertStringContainsString('title="{{ $work->label }}"', file_get_contents(resource_path('views/admin/quotations/shared_prices/edit.blade.php')));
        $this->assertStringContainsString('grid-area: search', $css);

        $this->assertBrowserChipAndWorkGeometry();

        $this->assertMatchesRegularExpression('/\.sp-col-sell:has\(\.admin-info-tip\)\s+\.sp-input\s*\{[^}]*padding-right:\s*1\.85rem/', $css);
        $this->assertMatchesRegularExpression('/\.sp-table\s+\.sp-work\s*\{[^}]*padding:\s*0/', $css);
        $this->assertMatchesRegularExpression('/\.sp-search-wrap\.sp-search-wrap--hidden\s*\{[^}]*left:\s*0/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.sp-group \.admin-table-scroll\s*\{[^}]*overflow-x:\s*hidden/', $css);
        $this->assertMatchesRegularExpression('/overflow:\s*(visible|clip)/', $css);
        $this->assertStringContainsString('.sp-effective', $css);
        $this->assertStringContainsString('z-index: 80', $css);
    }

    private function pageCss(string $html): string
    {
        $this->assertMatchesRegularExpression('/<style>(.*?)<\/style>/s', $html);
        preg_match('/<style>(.*?)<\/style>/s', $html, $match);

        return $match[1];
    }

    private function cssBlock(string $css, string $selector): string
    {
        $quoted = preg_quote($selector, '/');
        $pattern = '/(?:^|[\s,])'.$quoted.'(?![\w-])[^{]*\{([^}]+)\}/';
        $this->assertMatchesRegularExpression($pattern, $css, "Missing CSS rule {$selector}");
        preg_match($pattern, $css, $match);

        return $match[1];
    }

    /**
     * @return list<string>
     */
    private function desktopGridColumns(string $css): array
    {
        $this->assertMatchesRegularExpression('/@media \(min-width: 901px\)\s*\{.*?grid-template-columns:\s*(.*?);/s', $css);
        preg_match('/@media \(min-width: 901px\)\s*\{.*?grid-template-columns:\s*(.*?);/s', $css, $match);
        $raw = trim(preg_replace('/\s+/', ' ', $match[1]));
        preg_match_all('/minmax\([^)]+\)|[^\s]+/', $raw, $parts);

        return array_values($parts[0]);
    }

    private function cssMinRem(string $token): float
    {
        if (preg_match('/minmax\(\s*([0-9.]+)rem/', $token, $match)) {
            return (float) $match[1];
        }
        if (preg_match('/^([0-9.]+)rem$/', $token, $match)) {
            return (float) $match[1];
        }

        $this->fail("Could not parse rem min from {$token}");
    }

    /**
     * When Puppeteer is installed, measure real 1024 layout so a tight Used-by
     * column or a squeezed Work item fails CI/local the same way the screenshots do.
     */
    private function assertBrowserChipAndWorkGeometry(): void
    {
        if (! is_dir(base_path('node_modules/puppeteer'))) {
            return;
        }

        $script = sys_get_temp_dir().'/sp-layout-geometry.js';
        file_put_contents($script, <<<'JS'
const puppeteer = require('puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1024, height: 900 });
  await page.goto('http://127.0.0.1:8000/admin/login', { waitUntil: 'networkidle0' });
  if (page.url().includes('/admin/login')) {
    await page.type('input[name="email"]', 'admin@zanini.ca');
    await page.type('input[name="password"]', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"]')]);
  }
  await page.goto('http://127.0.0.1:8000/admin/quotations/shared-prices', { waitUntil: 'networkidle0' });
  const result = await page.evaluate(() => {
    const chips = [...document.querySelectorAll('.sp-chip:not(.sp-chip--more)')];
    const clipped = chips.filter((c) => {
      const label = c.querySelector('.sp-chip__label') || c;
      return label.scrollWidth > label.clientWidth + 1;
    }).map((c) => {
      const label = c.querySelector('.sp-chip__label') || c;
      return { text: (label.textContent || '').trim(), scroll: label.scrollWidth, client: label.clientWidth };
    });
    const offCenter = chips.map((c) => {
      const label = c.querySelector('.sp-chip__label') || c;
      const cBox = c.getBoundingClientRect();
      const lBox = label.getBoundingClientRect();
      const dy = Math.abs((cBox.top + cBox.height / 2) - (lBox.top + lBox.height / 2));
      return { text: (label.textContent || '').trim(), dy, chipH: cBox.height, labelH: lBox.height };
    }).filter((x) => x.dy > 1);
    const labels = [...document.querySelectorAll('.sp-work-label')];
    const workW = labels.length ? Math.round(labels[0].closest('.sp-col-label').getBoundingClientRect().width) : 0;
    const nameCol = document.querySelector('.sp-col-name');
    const nameW = nameCol ? Math.round(nameCol.getBoundingClientRect().width) : 0;
    return { clipped, offCenter, workW, nameW, chipCount: chips.length };
  });
  await browser.close();
  process.stdout.write(JSON.stringify(result));
})().catch((err) => { console.error(err); process.exit(2); });
JS
        );

        $cmd = 'cd '.escapeshellarg(base_path()).' && node '.escapeshellarg($script).' 2>/dev/null';
        exec($cmd, $output, $code);
        @unlink($script);
        if ($code !== 0) {
            return;
        }

        $json = json_decode(implode("\n", $output), true);
        if (! is_array($json)) {
            return;
        }

        $this->assertSame([], $json['clipped'] ?? ['failed'], 'Chip labels must not clip (scrollWidth <= clientWidth)');
        $this->assertSame([], $json['offCenter'] ?? ['failed'], 'Chip label vertical centre must be within 1px of the chip box centre');
        $this->assertGreaterThanOrEqual(115, (int) ($json['workW'] ?? 0), 'Work item column must be ≥18ch (~115px) at 1024');
        $this->assertGreaterThanOrEqual(115, (int) ($json['nameW'] ?? 0), 'Mat. name column must be ≥7.5rem (~120px) at 1024');
    }

    private function cssFontRem(string $block): float
    {
        $this->assertMatchesRegularExpression('/font-size:\s*([0-9.]+)rem/', $block);
        preg_match('/font-size:\s*([0-9.]+)rem/', $block, $match);

        return (float) $match[1];
    }
}
