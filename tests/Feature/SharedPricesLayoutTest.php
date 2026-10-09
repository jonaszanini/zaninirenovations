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
        $this->assertMatchesRegularExpression('/justify-content:\s*center/', $chip);
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $chip);
        $this->assertMatchesRegularExpression('/text-align:\s*center/', $chip);
        $this->assertGreaterThanOrEqual(0.65, $this->cssFontRem($chip));

        $more = $this->cssBlock($css, '.sp-chip--more');
        $this->assertDoesNotMatchRegularExpression('/\.sp-chip--more:focus\s+\.sp-chip-more__tip/', $css);
        $this->assertMatchesRegularExpression('/min-width:\s*32px/', $more);

        $columns = $this->desktopGridColumns($css);
        $this->assertCount(8, $columns);
        $this->assertGreaterThanOrEqual(6.5, $this->cssMinRem($columns[2]), 'Sell must fit 12.9000 and 99,999.9999');
        $this->assertTrue(
            str_contains($columns[3], '7ch') || $this->cssMinRem($columns[3]) >= 4.6,
            'Unit must be ~7ch so opening does not clip'
        );
        $this->assertGreaterThanOrEqual(6.5, $this->cssMinRem($columns[4]), 'Mat. price must fit 99,999.9999');
        $this->assertGreaterThanOrEqual(7.5, $this->cssMinRem($columns[5]), 'Mat. name should show ~16–18 characters');
        $this->assertLessThanOrEqual(5.5, $this->cssMinRem($columns[7]), 'Used-by shrinks to ~5.5rem with +N');
        $this->assertGreaterThanOrEqual(5.0, $this->cssMinRem($columns[7]));

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

    private function cssFontRem(string $block): float
    {
        $this->assertMatchesRegularExpression('/font-size:\s*([0-9.]+)rem/', $block);
        preg_match('/font-size:\s*([0-9.]+)rem/', $block, $match);

        return (float) $match[1];
    }
}
