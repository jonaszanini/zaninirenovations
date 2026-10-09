<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use Tests\IntegrationTestCase;

/**
 * v1 output must match fixtures generated from main bc380f47.
 */
class ClientProjectQuotationPdfFoldV1IdentityTest extends IntegrationTestCase
{
    public function test_named_main_regressions_match_committed_fixtures(): void
    {
        $named = $this->loadFixture('named.json');
        $this->assertEquals(75.33, $named['sq_2x_33_33']['total']);
        $this->assertEquals(1695, $named['material_1000_debris_565']['total']);
        $this->assertEquals(1243, $named['material_1000_commute_113']['total']);
        $this->assertEquals(1778.01, $named['paving_1000_labour_613_commute']['total']);
        $this->assertEquals(130, $named['cash_fence_labour_plus_debris']['hst']);

        $split = $named['labour_and_electrical_labour_split']['lines'];
        $this->assertEqualsWithDelta(550.0, $split[0]['amount'], 0.001);
        $this->assertEqualsWithDelta(350.0, $split[1]['amount'], 0.001);

        foreach ($named as $key => $fx) {
            foreach ([1, null] as $version) {
                $this->assertFixtureMatches($fx, $key, $version);
            }
        }
    }

    public function test_sq_2x_33_33_goes_through_the_real_sq_linked_path(): void
    {
        $named = $this->loadFixture('named.json');
        $fx = $named['sq_2x_33_33'];
        $this->assertNotEmpty($fx['sq_materials'] ?? []);

        $sq = StandardQuotation::query()->create([
            'quote_number' => 'SQ-V1-'.uniqid(),
            'client_name' => 'Identity',
            'materials' => $fx['sq_materials'],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'labour_use_fixed' => false,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'pdf_fold_version' => 1,
        ]);

        foreach ([1, null] as $version) {
            $quote = new ClientProjectQuotation([
                'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                'source_id' => $sq->id,
                'items' => $fx['items'],
                'pdf_fold_version' => $version,
            ]);
            $quote->exists = true;
            $this->assertTrue($quote->usesLegacyPdfFold());
            $this->assertEquals(75.33, $quote->grandTotal(), 'SQ-linked total version '.json_encode($version));
            $this->assertEquals(8.67, $quote->hst(), 'SQ-linked HST version '.json_encode($version));
            $this->assertTrue($this->sameSnapshot($quote, $fx), 'SQ-linked snapshot version '.json_encode($version));
        }
    }

    public function test_v2_sq_2x_33_33_linked_path_matches_editor(): void
    {
        $named = $this->loadFixture('named.json');
        $fx = $named['sq_2x_33_33'];
        $this->assertNotEmpty($fx['sq_materials'] ?? []);

        $sq = StandardQuotation::query()->create([
            'quote_number' => 'SQ-V2-'.uniqid(),
            'client_name' => 'Identity v2',
            'materials' => $fx['sq_materials'],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'labour_use_fixed' => false,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'pdf_fold_version' => 2,
        ]);

        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $sq->id,
            'items' => $sq->summaryPdfLineItems(),
            'pdf_fold_version' => 2,
        ]);
        $this->assertTrue($quote->usesPdfFoldV2());
        $this->assertFalse($quote->usesCurrentPdfFold());
        $this->assertEquals(8.67, $quote->editorHst());
        $this->assertEquals(8.67, $quote->pdfHst());
        $this->assertEquals(8.67, $quote->hst());
        $this->assertEquals(75.33, $quote->editorGrandTotal());
        $this->assertEquals(75.33, $quote->pdfGrandTotal());
        $this->assertEquals(75.33, $quote->grandTotal());
    }

    public function test_v3_sq_2x_33_33_linked_path_matches_editor(): void
    {
        $named = $this->loadFixture('named.json');
        $fx = $named['sq_2x_33_33'];
        $this->assertNotEmpty($fx['sq_materials'] ?? []);

        $sq = StandardQuotation::query()->create([
            'quote_number' => 'SQ-V3-'.uniqid(),
            'client_name' => 'Identity v3',
            'materials' => $fx['sq_materials'],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'labour_use_fixed' => false,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'pdf_fold_version' => 3,
        ]);

        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $sq->id,
            'items' => $sq->summaryPdfLineItems(),
            'pdf_fold_version' => 3,
        ]);
        $this->assertTrue($quote->usesCurrentPdfFold());
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $this->assertEquals($quote->editorHst(), $quote->hst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->grandTotal());
    }

    public function test_same_lines_detects_hst_included_and_hst_amount_flips(): void
    {
        $fx = $this->loadFixture('named.json')['sq_2x_33_33'];
        $quote = $this->quoteFromFixture($fx, 'standard', 1);
        $this->assertTrue($this->sameSnapshot($quote, $fx));

        $flippedIncluded = $fx;
        $flippedIncluded['lines'][0]['hst_included'] = ! ((bool) ($flippedIncluded['lines'][0]['hst_included'] ?? false));
        $this->assertFalse($this->sameSnapshot($quote, $flippedIncluded));

        $flippedAmount = $fx;
        $flippedAmount['lines'][0]['hst_amount'] = 99.99;
        $this->assertFalse($this->sameSnapshot($quote, $flippedAmount));
    }

    /**
     * @dataProvider sourceFixtureFiles
     */
    public function test_two_thousand_random_quotes_per_source_match_main(string $source): void
    {
        $rows = $this->loadFixture($source.'.json');
        $this->assertCount(2000, $rows, $source);
        $sawIncluded = false;
        $sawHstAmount = false;
        $sawNullVersion = false;
        $mismatches = 0;
        foreach ($rows as $fx) {
            foreach ($fx['items'] as $item) {
                if (! empty($item['hst_included'])) {
                    $sawIncluded = true;
                }
                if (array_key_exists('hst_amount', $item)) {
                    $sawHstAmount = true;
                }
            }
            $quote = $this->quoteFromFixture($fx, $source);
            if ($quote->pdf_fold_version === null) {
                $sawNullVersion = true;
            }
            if (! $this->sameSnapshot($quote, $fx)) {
                $mismatches++;
            }
        }
        $this->assertSame(0, $mismatches, $source.' v1 drifted from main fixtures');
        $this->assertTrue($sawIncluded, $source.' fixtures must vary hst_included');
        $this->assertTrue($sawHstAmount, $source.' fixtures must plant hst_amount for v1 to ignore');
        $this->assertTrue($sawNullVersion, $source.' must cover persisted null pdf_fold_version');
    }

    public function test_source_fixture_files_are_not_byte_identical(): void
    {
        $hashes = [];
        foreach (array_keys($this->sourceFixtureFiles()) as $source) {
            $hashes[$source] = hash_file('sha256', base_path('tests/fixtures/pdf-fold-v1/'.$source.'.json'));
        }
        $this->assertCount(count($hashes), array_unique($hashes), 'Each source needs its own seed');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function sourceFixtureFiles(): array
    {
        return [
            'standard' => ['standard'],
            'fence' => ['fence'],
            'low_deck' => ['low_deck'],
            'basement' => ['basement'],
            'flooring' => ['flooring'],
            'paving' => ['paving'],
        ];
    }

    /**
     * @param  array<string, mixed>  $fx
     */
    private function assertFixtureMatches(array $fx, string $key, ?int $version = 1): void
    {
        $quote = $this->quoteFromFixture($fx, (string) $fx['source'], $version);
        $this->assertTrue($this->sameSnapshot($quote, $fx), $key.' must match main (version '.json_encode($version).')');
        $this->assertEquals($fx['total'], $quote->grandTotal(), $key.' total');
        $this->assertEquals($fx['hst'], $quote->hst(), $key.' hst');
    }

    /**
     * @param  array<string, mixed>  $fx
     */
    private function quoteFromFixture(array $fx, string $source, mixed $version = 'auto'): ClientProjectQuotation
    {
        if ($version === 'auto') {
            $version = array_key_exists('i', $fx)
                ? (((int) $fx['i'] % 2 === 0) ? null : 1)
                : 1;
        }

        $attrs = [
            'source_type' => $source,
            'items' => $fx['items'],
            'subtotal_override' => $fx['override'] ?? null,
        ];
        if ($version !== null) {
            $attrs['pdf_fold_version'] = $version;
        }

        $quote = new ClientProjectQuotation($attrs);
        $quote->exists = true;
        $this->assertTrue($quote->usesLegacyPdfFold());

        return $quote;
    }

    /**
     * @param  array<string, mixed>  $fx
     */
    private function sameSnapshot(ClientProjectQuotation $quote, array $fx): bool
    {
        $lines = array_values(array_map(static function (array $row): array {
            $out = [
                'description' => (string) ($row['description'] ?? ''),
                'amount' => round((float) ($row['amount'] ?? 0), 2),
                'apply_hst' => (bool) ($row['apply_hst'] ?? false),
            ];
            if (array_key_exists('hst_taxable_amount', $row)) {
                $out['hst_taxable_amount'] = round((float) $row['hst_taxable_amount'], 2);
            }
            if (! empty($row['hst_included'])) {
                $out['hst_included'] = true;
            }
            if (array_key_exists('hst_amount', $row)) {
                $out['hst_amount'] = round((float) $row['hst_amount'], 2);
            }
            if (! empty($row['is_heading'])) {
                $out['is_heading'] = true;
            }

            return $out;
        }, $quote->pdfLineItems()));

        return $this->sameLines($lines, $fx['lines'])
            && abs($quote->hst() - (float) $fx['hst']) < 0.001
            && abs($quote->grandTotal() - (float) $fx['total']) < 0.001
            && abs($quote->pdfHst() - (float) $fx['pdf_hst']) < 0.001
            && abs($quote->pdfGrandTotal() - (float) $fx['pdf_total']) < 0.001;
    }

    /**
     * @param  list<array<string, mixed>>  $a
     * @param  list<array<string, mixed>>  $b
     */
    private function sameLines(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach ($a as $i => $row) {
            if ((string) ($row['description'] ?? '') !== (string) ($b[$i]['description'] ?? '')) {
                return false;
            }
            if (abs((float) ($row['amount'] ?? 0) - (float) ($b[$i]['amount'] ?? 0)) > 0.001) {
                return false;
            }
            if ((bool) ($row['apply_hst'] ?? false) !== (bool) ($b[$i]['apply_hst'] ?? false)) {
                return false;
            }
            if ((bool) ($row['hst_included'] ?? false) !== (bool) ($b[$i]['hst_included'] ?? false)) {
                return false;
            }
            $ha = array_key_exists('hst_amount', $row) ? (float) $row['hst_amount'] : null;
            $hb = array_key_exists('hst_amount', $b[$i]) ? (float) $b[$i]['hst_amount'] : null;
            if ($ha === null xor $hb === null) {
                return false;
            }
            if ($ha !== null && abs($ha - $hb) > 0.001) {
                return false;
            }
            $ta = array_key_exists('hst_taxable_amount', $row) ? (float) $row['hst_taxable_amount'] : null;
            $tb = array_key_exists('hst_taxable_amount', $b[$i]) ? (float) $b[$i]['hst_taxable_amount'] : null;
            if ($ta === null xor $tb === null) {
                return false;
            }
            if ($ta !== null && abs($ta - $tb) > 0.001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function loadFixture(string $name): array
    {
        $path = base_path('tests/fixtures/pdf-fold-v1/'.$name);
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);

        return $data;
    }
}
