<?php

namespace Tests\Unit;

use App\Services\AdditionalCostsService;
use PHPUnit\Framework\TestCase;

class AdditionalCostsServiceTest extends TestCase
{
    public function test_tax_included_row_contributes_pre_tax_and_matching_tax_in_total(): void
    {
        $rows = [[
            'description' => 'Dumpster',
            'amount' => 113.0,
            'hst_included' => true,
        ]];

        $this->assertSame(100.0, AdditionalCostsService::materialsPreTaxTotal($rows));
        $this->assertSame(13.0, AdditionalCostsService::materialsHstTotal($rows));
        $this->assertSame(113.0, AdditionalCostsService::taxInclusiveTotal($rows));
    }

    public function test_before_tax_row_adds_hst_to_grand_total(): void
    {
        $rows = [[
            'description' => 'Permit',
            'amount' => 100.0,
            'hst_included' => false,
        ]];

        $this->assertSame(100.0, AdditionalCostsService::materialsPreTaxTotal($rows));
        $this->assertSame(13.0, AdditionalCostsService::materialsHstTotal($rows));
        $this->assertSame(113.0, AdditionalCostsService::taxInclusiveTotal($rows));
    }

    public function test_empty_and_zero_rows_are_dropped(): void
    {
        $rows = AdditionalCostsService::normalizeRows([
            ['description' => '', 'amount' => '', 'hst_included' => '1'],
            ['description' => 'Keep', 'amount' => 25, 'hst_included' => '0'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('Keep', $rows[0]['description']);
        $this->assertFalse($rows[0]['hst_included']);
        $this->assertSame(AdditionalCostsService::CATEGORY_MATERIALS, $rows[0]['category']);
    }

    public function test_project_lines_are_tax_inclusive_and_prefixed(): void
    {
        $lines = AdditionalCostsService::projectLineItems([
            ['description' => 'Dumpster', 'amount' => 100, 'hst_included' => false],
        ]);

        $this->assertCount(1, $lines);
        $this->assertSame('Additional cost — Dumpster', $lines[0]['description']);
        $this->assertSame(113.0, $lines[0]['amount']);
        $this->assertTrue($lines[0]['hst_included']);
        $this->assertFalse($lines[0]['apply_hst']);
    }

    public function test_visible_project_lines_prefix_the_trade_heading(): void
    {
        $lines = AdditionalCostsService::visibleProjectLineItems([
            ['description' => 'Licensed electrician', 'amount' => 100, 'hst_included' => false],
        ], 'Electrical work');

        $this->assertCount(1, $lines);
        $this->assertSame('Electrical work — Licensed electrician', $lines[0]['description']);
        $this->assertSame(100.0, $lines[0]['amount']);
        $this->assertFalse($lines[0]['hst_included']);
        $this->assertTrue($lines[0]['apply_hst']);
    }

    public function test_visible_project_lines_convert_tax_included_to_pre_tax(): void
    {
        $lines = AdditionalCostsService::visibleProjectLineItems([
            ['description' => 'Rough-in', 'amount' => 113.0, 'hst_included' => true],
        ], 'Plumbing work');

        $this->assertSame(100.0, $lines[0]['amount']);
        $this->assertTrue($lines[0]['apply_hst']);
        $this->assertFalse($lines[0]['hst_included']);
    }

    public function test_visible_project_lines_do_not_double_prefix(): void
    {
        $lines = AdditionalCostsService::visibleProjectLineItems([
            ['description' => 'Electrical work — Already prefixed', 'amount' => 50, 'hst_included' => true],
        ], 'Electrical work');

        $this->assertSame('Electrical work — Already prefixed', $lines[0]['description']);
    }

    public function test_labour_category_is_excluded_from_materials_totals(): void
    {
        $rows = [
            ['description' => 'Dumpster', 'amount' => 100.0, 'hst_included' => false, 'category' => 'materials'],
            ['description' => 'Extra hours', 'amount' => 200.0, 'hst_included' => false, 'category' => 'labor'],
        ];

        $this->assertSame(100.0, AdditionalCostsService::materialsPreTaxTotal($rows));
        $this->assertSame(200.0, AdditionalCostsService::laborPreTaxTotal($rows));
        $this->assertSame(13.0, AdditionalCostsService::materialsHstTotal($rows));
        $this->assertSame(26.0, AdditionalCostsService::laborHstTotal($rows));
        $this->assertSame(226.0, AdditionalCostsService::taxInclusiveTotalForCategory($rows, AdditionalCostsService::CATEGORY_LABOUR));
        $this->assertSame(339.0, AdditionalCostsService::taxInclusiveTotal($rows));
    }

    public function test_project_lines_prefix_labour_separately(): void
    {
        $lines = AdditionalCostsService::projectLineItems([
            ['description' => 'Dumpster', 'amount' => 100, 'hst_included' => false, 'category' => 'materials'],
            ['description' => 'Extra hours', 'amount' => 100, 'hst_included' => false, 'category' => 'labour'],
        ]);

        $this->assertCount(2, $lines);
        $this->assertSame('Additional cost — Dumpster', $lines[0]['description']);
        $this->assertSame('Additional labour — Extra hours', $lines[1]['description']);
        $this->assertSame(113.0, $lines[0]['amount']);
        $this->assertSame(113.0, $lines[1]['amount']);
    }

    public function test_missing_category_defaults_to_materials(): void
    {
        $rows = AdditionalCostsService::normalizeRows([
            ['description' => 'Permit', 'amount' => 50, 'hst_included' => '1'],
        ]);

        $this->assertSame(AdditionalCostsService::CATEGORY_MATERIALS, $rows[0]['category']);
        $this->assertSame('materials', AdditionalCostsService::normalizeCategory(null));
        $this->assertSame('labour', AdditionalCostsService::normalizeCategory('Labor'));
    }

    public function test_normalize_rows_accepts_thousands_separators(): void
    {
        $rows = AdditionalCostsService::normalizeRows([
            ['description' => 'Rough-in', 'amount' => '3,850.00', 'hst_included' => '1'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(3850.0, $rows[0]['amount']);
    }
}
