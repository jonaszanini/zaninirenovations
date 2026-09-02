<?php

namespace Tests\Unit\Models;

use App\Models\StandardQuotation;
use Tests\TestCase;

class StandardQuotationPdfLineItemsTest extends TestCase
{
    public function test_detailed_pdf_collapses_materials_into_one_line(): void
    {
        $quote = new StandardQuotation([
            'materials' => [
                [
                    'short_name' => 'Plywood',
                    'description' => '1/2-inch x 4-feet x 8 feet Sanded Birch Plywood',
                    'unit_price' => 48.98,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => '2x2',
                    'description' => '',
                    'unit_price' => 3.88,
                    'quantity' => 3,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => 'Stainer',
                    'description' => '',
                    'unit_price' => 25,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => 'Electrical work',
                    'description' => '',
                    'unit_price' => 650,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            'labour_hourly_rate' => 60,
            'labour_mode' => 'days',
            'labour_quantity' => 1,
            'labour_use_fixed' => false,
            'contingency_enabled' => true,
            'contingency_percent' => 20,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame(['Material'], array_values(array_filter(
            $descriptions,
            fn ($d) => str_starts_with((string) $d, 'Material')
        )));
        $this->assertContains('Material', $descriptions);
        $this->assertNotContains('Plywood', $descriptions);
        $this->assertFalse((bool) preg_grep('/Plywood|2x2|Stainer|Electrical work/', $descriptions));

        $material = collect($lines)->firstWhere('description', 'Material');
        $this->assertNotNull($material);
        $this->assertEqualsWithDelta(48.98 + 11.64 + 25.0 + 650.0, $material['amount'], 0.01);
        $this->assertTrue($material['apply_hst']);

        $this->assertNotFalse(collect($lines)->first(fn ($row) => str_starts_with((string) $row['description'], 'Contingency')));
        $this->assertNotFalse(collect($lines)->first(fn ($row) => str_starts_with((string) $row['description'], 'Labour')));
    }

    public function test_detailed_pdf_lists_electrical_work_as_its_own_line(): void
    {
        $quote = new StandardQuotation([
            'materials' => [
                [
                    'short_name' => 'Plywood',
                    'description' => '1/2-inch birch',
                    'unit_price' => 48.98,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'line_kind' => StandardQuotation::LINE_KIND_ELECTRICAL,
                    'entry_mode' => 'electrical',
                    'description' => StandardQuotation::ELECTRICAL_LINE_DESCRIPTION,
                    'unit_price' => 650,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'labour_use_fixed' => false,
            'contingency_enabled' => false,
            'contingency_percent' => 0,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $byDesc = collect($lines)->keyBy('description');

        $this->assertEqualsWithDelta(48.98, $byDesc['Material']['amount'], 0.01);
        $this->assertTrue($byDesc['Material']['apply_hst']);
        $this->assertEqualsWithDelta(650.0, $byDesc[StandardQuotation::ELECTRICAL_LINE_DESCRIPTION]['amount'], 0.01);
        $this->assertTrue($byDesc[StandardQuotation::ELECTRICAL_LINE_DESCRIPTION]['apply_hst']);
        $this->assertSame(
            ['Material', StandardQuotation::ELECTRICAL_LINE_DESCRIPTION],
            array_column($lines, 'description')
        );
    }

    public function test_detailed_pdf_splits_hst_exempt_materials(): void
    {
        $quote = new StandardQuotation([
            'materials' => [
                [
                    'short_name' => 'Taxed lumber',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => 'Exempt item',
                    'unit_price' => 40,
                    'quantity' => 1,
                    'apply_hst' => false,
                ],
            ],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'contingency_enabled' => false,
            'contingency_percent' => 0,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $byDesc = collect($lines)->keyBy('description');

        $this->assertEqualsWithDelta(100.0, $byDesc['Material']['amount'], 0.01);
        $this->assertTrue($byDesc['Material']['apply_hst']);
        $this->assertEqualsWithDelta(40.0, $byDesc['Material — HST exempt']['amount'], 0.01);
        $this->assertFalse($byDesc['Material — HST exempt']['apply_hst']);
    }
}
