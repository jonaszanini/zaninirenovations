<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use Tests\TestCase;

/**
 * v1 output must match fixtures generated from main bc380f47.
 */
class ClientProjectQuotationPdfFoldV1IdentityTest extends TestCase
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
            $this->assertFixtureMatches($fx, $key);
        }
    }

    /**
     * @dataProvider sourceFixtureFiles
     */
    public function test_two_thousand_random_quotes_per_source_match_main(string $source): void
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
        $this->assertSame(0, $mismatches, $source.' v1 drifted from main fixtures');
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
        $this->assertTrue($this->sameSnapshot($quote, $fx), $key.' must match main');
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
            'pdf_fold_version' => 1,
        ]);
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
