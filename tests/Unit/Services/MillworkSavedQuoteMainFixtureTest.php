<?php

namespace Tests\Unit\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\MillworkQuotationCalculator;
use Tests\TestCase;

/**
 * Saved v1 and v2 quotes must open at main's totals, line items, and PDF scope.
 */
class MillworkSavedQuoteMainFixtureTest extends TestCase
{
    /**
     * @dataProvider fixtureProvider
     */
    public function test_saved_quote_matches_main_fixture(string $name): void
    {
        $path = base_path('tests/fixtures/millwork-saved-quotes-main/'.$name.'.json');
        $this->assertFileExists($path);
        $fixture = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($fixture);

        $calc = (new MillworkQuotationCalculator)->calculate(
            $fixture['inputs'],
            $this->settings(),
            (int) $fixture['version']
        );
        $quote = new MillworkQuotation([
            'cut_plan_version' => (int) $fixture['version'],
            'inputs' => $fixture['inputs'],
            'calculation' => $calc,
        ]);
        $actual = $this->snapshot($calc, $quote->scopeDescriptionForPdf());
        $expected = $fixture['expected'];

        $this->assertEqualsWithDelta(
            (float) $expected['summary']['grand_total'],
            (float) $actual['summary']['grand_total'],
            0.01,
            $name.' total'
        );
        $this->assertEqualsWithDelta(
            (float) $expected['summary']['labor_pre_hst'],
            (float) $actual['summary']['labor_pre_hst'],
            0.01,
            $name.' labour'
        );
        $this->assertEqualsWithDelta(
            (float) $expected['summary']['materials_pre_hst'],
            (float) $actual['summary']['materials_pre_hst'],
            0.01,
            $name.' materials'
        );
        $this->assertEquals($this->normalizeRows($expected['labor']), $this->normalizeRows($actual['labor']), $name.' labor lines');
        $this->assertEquals($this->normalizeRows($expected['materials']), $this->normalizeRows($actual['materials']), $name.' material lines');
        $this->assertSame($expected['scope_labels'], $actual['scope_labels'], $name.' scope labels');
        $this->assertSame($expected['pdf_scope'], $actual['pdf_scope'], $name.' PDF');
        if (array_key_exists('preview_skipped', $expected)) {
            $this->assertSame(
                (bool) $expected['preview_skipped'],
                (bool) ($calc['cut_plan']['preview_skipped'] ?? false),
                $name.' preview_skipped'
            );
        }
        if ($name === 'v1_retyped_fraction_pdf') {
            $this->assertStringContainsString('23.5" W', $actual['pdf_scope']);
            $this->assertStringNotContainsString('23 1/2" W', $actual['pdf_scope']);
        }
        if ($name === 'v2_wainscot_300in_run') {
            $this->assertFalse((bool) ($calc['cut_plan']['preview_skipped'] ?? false));
            $this->assertGreaterThan(1000, (float) $actual['summary']['grand_total']);
        }
        if ($name === 'v2_bench_blank_height') {
            $this->assertEqualsWithDelta(0.0, (float) $actual['summary']['grand_total'], 0.01);
            $this->assertStringNotContainsString('18"', implode(' ', $actual['scope_labels']));
        }
    }

    /**
     * @return list<array{0: string}>
     */
    public function fixtureProvider(): array
    {
        return [
            ['v1_saved_trim_cabinet'],
            ['v1_retyped_fraction_pdf'],
            ['v2_saved_ft_trim_leftover'],
            ['v2_saved_shelf_dot75'],
            ['v2_bench_blank_height'],
            ['v2_mantel_ft_defaults'],
            ['v2_closet_ft_defaults'],
            ['v2_wainscot_300in_run'],
        ];
    }

    /**
     * @param  array<string, mixed>  $calc
     * @return array<string, mixed>
     */
    private function snapshot(array $calc, string $scope): array
    {
        $mapLabor = static function (array $row): array {
            return [
                'key' => $row['key'] ?? '',
                'label' => $row['label'] ?? '',
                'hours' => $row['hours'] ?? 0,
                'amount' => $row['amount'] ?? 0,
            ];
        };
        $mapMat = static function (array $row): array {
            return [
                'key' => $row['key'] ?? '',
                'label' => $row['label'] ?? '',
                'qty' => $row['qty'] ?? 0,
                'unit_price' => $row['unit_price'] ?? 0,
                'amount' => $row['amount'] ?? 0,
            ];
        };

        return [
            'summary' => [
                'labor_pre_hst' => $calc['summary']['labor_pre_hst'] ?? 0,
                'materials_pre_hst' => $calc['summary']['materials_pre_hst'] ?? 0,
                'subtotal_pre_hst' => $calc['summary']['subtotal_pre_hst'] ?? 0,
                'hst' => $calc['summary']['hst'] ?? 0,
                'grand_total' => $calc['summary']['grand_total'] ?? 0,
            ],
            'labor' => array_map($mapLabor, $calc['labor'] ?? []),
            'materials' => array_map($mapMat, $calc['materials'] ?? []),
            'scope_labels' => $calc['scope']['labels'] ?? [],
            'pdf_scope' => $scope,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        return array_map(static function (array $row): array {
            foreach (['hours', 'amount', 'qty', 'unit_price'] as $key) {
                if (array_key_exists($key, $row)) {
                    $row[$key] = round((float) $row[$key], 4);
                }
            }

            return $row;
        }, $rows);
    }

    private function settings(): MillworkSettings
    {
        $s = new MillworkSettings();
        $s->owner_hourly_rate = 85;
        $s->helper_hourly_rate = 45;
        $s->hours_per_day = 8;
        $s->productivity = MillworkSettings::defaultProductivity();
        $s->catalog = MillworkSettings::defaultCatalog();
        $s->cut_settings = MillworkSettings::defaultCutSettings();

        return $s;
    }
}
