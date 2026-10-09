<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use Tests\TestCase;

/**
 * v2 output must match fixtures generated from main #60 (f6ed7f91).
 */
class ClientProjectQuotationPdfFoldV2IdentityTest extends TestCase
{
    public function test_named_main_v2_regressions_match_committed_fixtures(): void
    {
        $named = $this->loadFixture('named.json');
        $this->assertArrayHasKey('tile_client_selected_materials', $named);
        $this->assertSame('Materials', $named['tile_client_selected_materials']['lines'][0]['description']);
        $this->assertArrayHasKey('paving_heading_missing_apply_hst', $named);
        $this->assertArrayNotHasKey('is_heading', $named['paving_heading_missing_apply_hst']['lines'][0]);
        $this->assertTrue($named['paving_heading_missing_apply_hst']['lines'][0]['apply_hst']);
        $this->assertArrayHasKey('standard_heading_missing_apply_hst', $named);
        $this->assertTrue((bool) ($named['standard_heading_missing_apply_hst']['lines'][0]['is_heading'] ?? false));
        $this->assertTrue($named['standard_heading_missing_apply_hst']['lines'][0]['apply_hst']);
        foreach ($named as $key => $fx) {
            $this->assertFixtureMatches($fx, $key);
        }
    }

    /**
     * @dataProvider sourceFixtureFiles
     */
    public function test_two_thousand_random_quotes_per_source_match_main_v2(string $source): void
    {
        $rows = $this->loadFixture($source.'.json');
        $this->assertCount(2000, $rows, $source);
        $mismatches = 0;
        foreach ($rows as $fx) {
            $quote = $this->quoteFromFixture($fx, $source);
            if (! $this->sameSnapshot($quote, $fx)) {
                $mismatches++;
            }
        }
        $this->assertSame(0, $mismatches, $source.' v2 drifted from main #60 fixtures');
    }

    public function test_source_fixture_files_are_not_byte_identical(): void
    {
        $hashes = [];
        foreach (array_keys($this->sourceFixtureFiles()) as $source) {
            $hashes[$source] = hash_file('sha256', base_path('tests/fixtures/pdf-fold-v2/'.$source.'.json'));
        }
        $this->assertCount(count($hashes), array_unique($hashes), 'Each source needs its own seed');
    }

    public function test_v2_custom_millwork_labour_keeps_the_saved_second_labour_row(): void
    {
        $named = $this->loadFixture('named.json');
        $fx = $named['custom_millwork_labour_plus_commute'];
        $quote = $this->quoteFromFixture($fx, ClientProjectQuotation::SOURCE_MILLWORK);
        $this->assertTrue($quote->usesPdfFoldV2());
        $this->assertContains('Labour', array_column($quote->pdfLineItems(), 'description'));
        $this->assertTrue($this->sameSnapshot($quote, $fx));
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
    private function assertFixtureMatches(array $fx, string $key): void
    {
        $quote = $this->quoteFromFixture($fx, (string) $fx['source']);
        $this->assertTrue($this->sameSnapshot($quote, $fx), $key.' must match main v2');
        $this->assertEquals($fx['total'], $quote->grandTotal(), $key.' total');
        $this->assertEquals($fx['hst'], $quote->hst(), $key.' hst');
    }

    /**
     * @param  array<string, mixed>  $fx
     */
    private function quoteFromFixture(array $fx, string $source): ClientProjectQuotation
    {
        $quote = new ClientProjectQuotation([
            'source_type' => $source,
            'items' => $fx['items'],
            'subtotal_override' => $fx['override'] ?? null,
            'pdf_fold_version' => 2,
        ]);
        $quote->exists = true;
        $this->assertTrue($quote->usesPdfFoldV2());
        $this->assertFalse($quote->usesCurrentPdfFold());
        $this->assertFalse($quote->usesLegacyPdfFold());

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
            if ((bool) ($row['is_heading'] ?? false) !== (bool) ($b[$i]['is_heading'] ?? false)) {
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
        $path = base_path('tests/fixtures/pdf-fold-v2/'.$name);
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);

        return $data;
    }
}
