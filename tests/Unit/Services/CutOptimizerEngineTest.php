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

    public function test_svg_draws_eighth_inch_kerf_between_rips(): void
    {
        $sheet = new GuillotineSheet('s', 48, 96, 0.125, 0, 0, 0, 0, 0, '4×8');
        $this->assertTrue($sheet->insert(new Part('a', 'A', 12, 96, 1, GrainDirection::LENGTHWISE), SplitHeuristic::VERTICAL_FIRST));
        $this->assertTrue($sheet->insert(new Part('b', 'B', 12, 96, 1, GrainDirection::LENGTHWISE), SplitHeuristic::VERTICAL_FIRST));
        $this->assertEqualsWithDelta(12.125, $sheet->placedParts[1]->x, 0.01);

        $svg = (new SvgVisualizer)->renderSheet($sheet);
        $this->assertStringContainsString('mw-cut-kerf', $svg);
        $this->assertStringContainsString('Kerf 1/8"', $svg);
        $this->assertSame('1/8"', SvgVisualizer::formatKerfLabel(0.125));
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
        $this->assertStringNotContainsString('4.5×', $thin);
        $this->assertStringNotContainsString('×35', $thin);
        $this->assertStringContainsString('48"', $svg);
        $this->assertStringContainsString('24"', $svg);
    }

    public function test_svg_hides_in_part_labels_on_narrow_strips(): void
    {
        $sheet = new GuillotineSheet('s4x8', 48, 96, 0.125, 0, 0, 0, 0, 0, '4×8');
        $this->assertTrue($sheet->insert(new Part('wide', 'Side panel', 23.75, 35.98, 1), SplitHeuristic::VERTICAL_FIRST));
        $this->assertTrue($sheet->insert(new Part('strip', 'Toe kick', 4.5, 35.98, 1), SplitHeuristic::VERTICAL_FIRST));

        $visual = new SvgVisualizer();
        $svg = $visual->renderSheet($sheet);
        preg_match_all('/<g class="mw-cut-part">.*?<\/g>\s*<\/g>/s', $svg, $groups);
        $this->assertGreaterThanOrEqual(2, count($groups[0]));
        $strip = $groups[0][1];
        $this->assertStringNotContainsString('<text', $strip);
        $this->assertStringNotContainsString('4.5×', $strip);
        $this->assertStringNotContainsString('rotate(-90', $strip);
        $this->assertSame('Toe kick', $visual->legend($sheet)[1]['label']);
    }

    public function test_optimize_with_offcuts_uses_inventory_before_full_sheet(): void
    {
        $engine = new CutOptimizerEngine();
        $offcut = new StockOption('oc', 'Leftover 24×48', 24, 48, 0, null, 99, 1);
        $result = $engine->optimizeWithOffcuts(
            [new Part('a', 'Small', 10, 12, 1, GrainDirection::ANY)],
            [new StockOption('full', '4×8', 48, 96, 72)],
            [$offcut],
            0.125,
            0,
            2
        );

        $this->assertSame([99], $result['used_offcut_ids']);
        $this->assertCount(1, $result['sheets']);
        $this->assertSame(99, $result['sheets'][0]->offcutId);
        $this->assertEqualsWithDelta(24.0, $result['sheets'][0]->width, 0.01);
        $this->assertEqualsWithDelta(48.0, $result['sheets'][0]->length, 0.01);
        $this->assertNotEmpty($result['sheets'][0]->leftoverRectangles(2));
    }
}
