<?php

namespace Tests\Unit\Services;

use App\Services\CutOptimizer\CutOptimizerEngine;
use App\Services\CutOptimizer\GrainDirection;
use App\Services\CutOptimizer\GuillotineSheet;
use App\Services\CutOptimizer\Part;
use App\Services\CutOptimizer\SplitHeuristic;
use App\Services\CutOptimizer\StockOption;
use App\Services\CutOptimizer\SvgVisualizer;
use Tests\TestCase;

class CutOptimizerEngineTest extends TestCase
{
    public function test_two_panels_fit_one_four_by_eight_with_kerf(): void
    {
        $engine = new CutOptimizerEngine();
        $parts = [
            new Part('a', 'Panel', 35, 41, 2, GrainDirection::ANY),
        ];
        $sheets = $engine->optimize($parts, [
            new StockOption('full', '4×8', 48, 96, 72),
        ], 0.125, 0, 2);

        $this->assertCount(1, $sheets);
        $this->assertCount(2, $sheets[0]->placedParts);
        $this->assertLessThan(50, $sheets[0]->wastePercentage());
    }

    public function test_kerf_prevents_fit_when_remainder_too_small(): void
    {
        $sheet = new GuillotineSheet('s', 10, 10, 0.5, 0, 0, 0, 0, 0);
        $part = new Part('p', 'P', 5.3, 10, 1, GrainDirection::ANY);
        $this->assertTrue($sheet->insert($part, SplitHeuristic::VERTICAL_FIRST));
        $second = new Part('q', 'Q', 5.3, 10, 1, GrainDirection::ANY);
        $this->assertFalse($sheet->insert($second, SplitHeuristic::VERTICAL_FIRST));
    }

    public function test_grain_lock_blocks_rotation(): void
    {
        $sheet = new GuillotineSheet('s', 48, 96, 0.125, 0, 0, 0, 0, 0);
        $part = new Part('p', 'Long', 50, 20, 1, GrainDirection::LENGTHWISE);
        $this->assertFalse($sheet->insert($part, SplitHeuristic::VERTICAL_FIRST));
        $any = new Part('q', 'Long any', 50, 20, 1, GrainDirection::ANY);
        $this->assertTrue($sheet->insert($any, SplitHeuristic::VERTICAL_FIRST));
    }

    public function test_edge_banding_shrinks_cut_size(): void
    {
        $part = new Part('p', 'Door', 12, 24, 1, GrainDirection::ANY, 0.02, 0.02, 0.02, 0.02);
        $this->assertEqualsWithDelta(11.96, $part->cuttingWidth, 0.0001);
        $this->assertEqualsWithDelta(23.96, $part->cuttingLength, 0.0001);
    }

    public function test_prefers_smaller_sheet_when_parts_fit(): void
    {
        $engine = new CutOptimizerEngine();
        $parts = [
            new Part('a', 'Small', 10, 12, 1, GrainDirection::ANY),
        ];
        $sheets = $engine->optimize($parts, [
            new StockOption('full', '4×8', 48, 96, 72),
            new StockOption('qtr', '2×4', 24, 48, 24),
        ], 0.125, 0, 2);

        $this->assertCount(1, $sheets);
        $this->assertEqualsWithDelta(24.0, $sheets[0]->width, 0.01);
        $this->assertEqualsWithDelta(48.0, $sheets[0]->length, 0.01);
    }

    public function test_svg_contains_part_label_and_dimensions(): void
    {
        $sheet = new GuillotineSheet('s', 48, 96, 0.125, 0, 0, 0, 0, 0, '4×8');
        $sheet->insert(new Part('p', 'Shelf A', 12, 24, 1), SplitHeuristic::VERTICAL_FIRST);
        $svg = (new SvgVisualizer)->renderSheet($sheet);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('Shelf A', $svg);
        $this->assertStringContainsString('mw-cut-part', $svg);
    }
}
