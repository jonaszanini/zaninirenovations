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
            'boards' => ['rows' => 22],
        ];
    }
}
