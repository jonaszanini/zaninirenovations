<?php

namespace Tests\Unit\Support;

use App\Support\LowDeckDiagramRenderer;
use Tests\TestCase;

class LowDeckDiagramRendererTest extends TestCase
{
    public function test_render_for_pdf_wraps_svgs_as_data_uri_images(): void
    {
        $html = LowDeckDiagramRenderer::renderForPdf($this->sampleGeometry())['foundations'];

        $this->assertStringStartsWith('<img src="data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('width="640"', $html);
        $this->assertStringContainsString('height="380"', $html);
    }

    public function test_foundations_svg_shows_pier_holes_posts_and_spacing(): void
    {
        $svg = LowDeckDiagramRenderer::render($this->sampleGeometry())['foundations'];

        $this->assertStringContainsString('12 in. pier holes', $svg);
        $this->assertStringContainsString('stroke="#0284c7"', $svg);
        $this->assertStringContainsString('fill="#024768"', $svg);
        $this->assertStringContainsString('4&#039;', $svg);
        $this->assertStringContainsString('5&#039;', $svg);
    }

    public function test_framing_svg_draws_members_with_thickness_and_spacing_label(): void
    {
        $svg = LowDeckDiagramRenderer::render($this->sampleGeometry())['framing'];

        $this->assertStringContainsString('members shown 1.5 in. wide', $svg);
        $this->assertStringContainsString('fill="#0f766e"', $svg);
        $this->assertStringContainsString('fill="#b45309"', $svg);
        $this->assertStringContainsString('13.96&quot;', $svg);
        $this->assertStringContainsString('max 15 in.', $svg);
        $this->assertStringContainsString('<line', $svg);
        $this->assertStringContainsString('stroke="#ea580c"', $svg);
    }

    public function test_decking_svg_draws_picture_frame_border_and_infill(): void
    {
        $svg = LowDeckDiagramRenderer::render($this->sampleGeometry())['decking'];

        $this->assertStringContainsString('45° miters', $svg);
        $this->assertStringContainsString('<polygon', $svg);
        $this->assertStringContainsString('fill="#c8a36b"', $svg);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleGeometry(): array
    {
        return [
            'run_ft' => 12.0,
            'span_ft' => 10.0,
            'joist_spacing_in' => 13.96,
            'max_joist_spacing_in' => 15,
            'blocks' => [
                'count' => 12,
                'grid_lines_run' => 4,
                'grid_lines_span' => 3,
                'positions_x_ft' => [0.0, 4.0, 8.0, 12.0],
                'positions_y_ft' => [0.0, 5.0, 10.0],
            ],
            'joists' => [
                'total_lines' => 8,
                'field_count' => 4,
                'edge_count' => 2,
                'rim_count' => 2,
                'field_spacing_in' => 13.96,
                'edge_inset_positions_x_ft' => [0.375, 11.625],
                'field_positions_x_ft' => [2.54, 4.705, 6.87, 9.035],
                'positions_x_ft' => [0.0, 0.375, 2.54, 4.705, 6.87, 9.035, 11.625, 12.0],
            ],
            'beams' => [
                'lines_total' => 5,
                'interior_count' => 1,
                'edge_count' => 2,
                'positions_y_ft' => [0.0, 0.375, 5.0, 9.625, 10.0],
            ],
            'joist_hangers' => [
                'count' => 8,
                'field_joist_count' => 4,
                'per_field_joist' => 2,
                'beam_y_ft' => 5.0,
                'positions_ft' => [
                    ['x_ft' => 2.54, 'y_ft' => 5.0],
                    ['x_ft' => 4.705, 'y_ft' => 5.0],
                    ['x_ft' => 6.87, 'y_ft' => 5.0],
                    ['x_ft' => 9.035, 'y_ft' => 5.0],
                ],
            ],
            'boards' => [
                'layout' => 'picture_frame',
                'total_boards' => 20,
                'infill_rows' => 16,
                'infill_full_rows' => 15,
                'infill_rip_rows' => 1,
                'board_width_ft' => 5.5 / 12.0,
                'board_gap_in' => 0.125,
                'infill_row_widths_in' => array_merge(
                    array_fill(0, 15, 5.5),
                    [4.125]
                ),
                'rows' => 16,
            ],
        ];
    }
}
