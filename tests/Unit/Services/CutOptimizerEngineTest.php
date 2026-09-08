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

    public function test_full_length_rip_uses_factory_edge_despite_trim(): void
    {
        $sheet = new GuillotineSheet('s', 48, 96, 0.125, 0.125, 0.125, 0.125, 0.125, 2.0, '4×8');
        $part = new Part('p', 'Top', 12, 96, 1, GrainDirection::LENGTHWISE);

        $this->assertTrue($sheet->insert($part, SplitHeuristic::VERTICAL_FIRST));
        $this->assertCount(1, $sheet->placedParts);
        $this->assertEqualsWithDelta(12.0, $sheet->placedParts[0]->width, 0.01);
        $this->assertEqualsWithDelta(96.0, $sheet->placedParts[0]->height, 0.01);
    }

    public function test_optimize_does_not_drop_full_length_parts_onto_two_by_four(): void
    {
        $engine = new CutOptimizerEngine();
        $parts = [
            new Part('top', 'Top', 12, 96, 6, GrainDirection::LENGTHWISE),
            new Part('front', 'Front', 3, 96, 3, GrainDirection::LENGTHWISE),
            new Part('end', 'End', 3, 12, 6, GrainDirection::ANY),
        ];
        $sheets = $engine->optimize($parts, [
            new StockOption('full', '4×8', 48, 96, 72),
            new StockOption('half', '4×4', 48, 48, 42),
            new StockOption('qtr', '2×4', 24, 48, 24),
        ], 0.125, 0.125, 2);

        $placed = 0;
        $usesFourByEight = false;
        foreach ($sheets as $sheet) {
            $placed += count($sheet->placedParts);
            if (abs($sheet->width - 48) < 0.2 && abs($sheet->length - 96) < 0.2) {
                $usesFourByEight = true;
            }
        }
        $this->assertSame(15, $placed);
        $this->assertTrue($usesFourByEight);
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
        $this->assertStringContainsString('clipPath', $svg);
    }

    public function test_svg_clips_and_numbers_thin_strips_instead_of_stacking_names(): void
    {
        $sheet = new GuillotineSheet('s2x4', 24, 48, 0.125, 0, 0, 0, 0, 0, '2×4');
        $sheet->insert(new Part('panel', 'FS1 top', 23.75, 38, 1), SplitHeuristic::VERTICAL_FIRST);
        $this->assertTrue($sheet->insert(new Part('e1', 'FS1 end', 12, 1.5, 1), SplitHeuristic::HORIZONTAL_FIRST));
        $this->assertTrue($sheet->insert(new Part('e2', 'FS1 end', 12, 1.5, 1), SplitHeuristic::HORIZONTAL_FIRST));

        $visual = new SvgVisualizer();
        $svg = $visual->renderSheet($sheet);
        $legend = $visual->legend($sheet);

        $this->assertStringContainsString('clip-path=', $svg);
        $this->assertStringNotContainsString('(rotated)</text>', $svg);
        $this->assertGreaterThanOrEqual(3, count($legend));
        $this->assertSame('FS1 end', $legend[1]['label']);

        preg_match_all('/<g class="mw-cut-part">.*?<\/g>\s*<\/g>/s', $svg, $groups);
        $this->assertGreaterThanOrEqual(3, count($groups[0]));
        $thin = $groups[0][1];
        $this->assertMatchesRegularExpression('/font-size="[0-9.]+"/', $thin);
        preg_match_all('/font-size="([0-9.]+)"/', $thin, $sizes);
        foreach ($sizes[1] as $size) {
            $this->assertLessThan(1.8, (float) $size);
        }
        $this->assertStringContainsString('48"', $svg);
        $this->assertStringContainsString('24"', $svg);
    }
}
