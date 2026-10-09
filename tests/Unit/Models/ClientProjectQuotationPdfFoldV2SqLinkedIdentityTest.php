<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use Tests\IntegrationTestCase;

/**
 * Saved v2 CPQs linked to a v2 SQ must match main #60 (f6ed7f91).
 * Uses the live source_id path so SQ leftover hst_amount is exercised.
 */
class ClientProjectQuotationPdfFoldV2SqLinkedIdentityTest extends IntegrationTestCase
{
    public function test_sq_linked_v2_quotes_match_main_fixtures(): void
    {
        $rows = $this->loadFixture();
        $this->assertGreaterThanOrEqual(200, count($rows));
        $multi = 0;
        foreach ($rows as $fx) {
            $works = $fx['sq']['works'] ?? [];
            if (is_array($works) && count($works) >= 2) {
                $multi++;
            }
        }
        $this->assertGreaterThanOrEqual(20, $multi, 'Need multi-job SQ-linked v2 fixtures');
        $remainder = 0;
        foreach ($rows as $key => $fx) {
            $works = $fx['sq']['works'] ?? [];
            $building = ! empty($fx['sq']['include_building']);
            $last = is_array($works) && $works !== [] ? $works[array_key_last($works)] : [];
            $lastLabour = (float) ($last['labour_fixed_amount'] ?? 0);
            if ($building && count($works) >= 2 && $lastLabour == 0.0) {
                $remainder++;
            }
        }
        $this->assertGreaterThanOrEqual(4, $remainder, 'Need several multi-job building-remainder fixtures');
        $this->assertArrayHasKey('sq_multi_job_remainder', $rows);
        $this->assertArrayHasKey('sq_multi_job_remainder_two_job', $rows);
        $this->assertArrayHasKey('sq_multi_job_remainder_four_job', $rows);
        $this->assertArrayHasKey('sq_multi_job_remainder_pct20', $rows);
        $this->assertArrayHasKey('sq_multi_job_remainder_with_contingency', $rows);
        $mismatches = 0;
        $sawHstAmount = false;
        foreach ($rows as $key => $fx) {
            $sqAttrs = $fx['sq'];
            $sqAttrs['quote_number'] = 'SQ-V2-ID-'.uniqid();
            if (! array_key_exists('materials', $sqAttrs)) {
                $sqAttrs['materials'] = [];
            }
            $sq = StandardQuotation::query()->create($sqAttrs);
            $this->assertTrue($sq->usesPdfFoldV2(), $key);
            $this->assertTrue($sq->usesNonLegacyPdfFold(), $key);

            $quote = new ClientProjectQuotation([
                'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                'source_id' => $sq->id,
                'pdf_fold_version' => 2,
                'items' => [],
            ]);
            $quote->exists = true;
            $this->assertTrue($quote->usesPdfFoldV2(), $key);

            foreach ($quote->pdfLineItems() as $row) {
                if (array_key_exists('hst_amount', $row)) {
                    $sawHstAmount = true;
                }
            }

            if (! $this->sameSnapshot($quote, $fx)) {
                $mismatches++;
            }
        }
        $this->assertSame(0, $mismatches, 'SQ-linked v2 drifted from main #60 fixtures');
        $this->assertTrue($sawHstAmount, 'v2 SQ-linked fixtures must emit leftover hst_amount');
    }

    public function test_v2_sq_still_emits_hst_amount_on_summary_lines(): void
    {
        $sq = new StandardQuotation([
            'pdf_fold_version' => 2,
            'materials' => [
                ['short_name' => 'A', 'unit_price' => 33.33, 'quantity' => 1, 'apply_hst' => true],
                ['short_name' => 'B', 'unit_price' => 33.33, 'quantity' => 1, 'apply_hst' => true],
            ],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'labour_use_fixed' => false,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
        ]);

        $lines = $sq->summaryPdfLineItems();
        $this->assertNotEmpty($lines);
        $this->assertArrayHasKey('hst_amount', $lines[0]);
        $this->assertEquals(8.66, round((float) $lines[0]['hst_amount'], 2));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(): array
    {
        $path = base_path('tests/fixtures/pdf-fold-v2/sq_linked.json');
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);

        return $data;
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

        if (count($lines) !== count($fx['lines'])) {
            return false;
        }
        foreach ($lines as $i => $row) {
            $b = $fx['lines'][$i];
            if ((string) ($row['description'] ?? '') !== (string) ($b['description'] ?? '')) {
                return false;
            }
            if (abs((float) ($row['amount'] ?? 0) - (float) ($b['amount'] ?? 0)) > 0.001) {
                return false;
            }
            if ((bool) ($row['apply_hst'] ?? false) !== (bool) ($b['apply_hst'] ?? false)) {
                return false;
            }
            if ((bool) ($row['hst_included'] ?? false) !== (bool) ($b['hst_included'] ?? false)) {
                return false;
            }
            if ((bool) ($row['is_heading'] ?? false) !== (bool) ($b['is_heading'] ?? false)) {
                return false;
            }
            $ta = array_key_exists('hst_taxable_amount', $row) ? (float) $row['hst_taxable_amount'] : null;
            $tb = array_key_exists('hst_taxable_amount', $b) ? (float) $b['hst_taxable_amount'] : null;
            if ($ta === null xor $tb === null) {
                return false;
            }
            if ($ta !== null && abs($ta - $tb) > 0.001) {
                return false;
            }
            $ha = array_key_exists('hst_amount', $row) ? (float) $row['hst_amount'] : null;
            $hb = array_key_exists('hst_amount', $b) ? (float) $b['hst_amount'] : null;
            if ($ha === null xor $hb === null) {
                return false;
            }
            if ($ha !== null && abs($ha - $hb) > 0.001) {
                return false;
            }
        }

        return abs($quote->hst() - (float) $fx['hst']) < 0.001
            && abs($quote->grandTotal() - (float) $fx['total']) < 0.001
            && abs($quote->pdfHst() - (float) $fx['pdf_hst']) < 0.001
            && abs($quote->pdfGrandTotal() - (float) $fx['pdf_total']) < 0.001;
    }
}
