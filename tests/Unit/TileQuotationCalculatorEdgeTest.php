<?php

namespace Tests\Unit;

use App\Models\TileEdge;
use App\Services\TileQuotationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TileQuotationCalculatorEdgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_edge_rounds_lf_up_to_8ft_pieces(): void
    {
        $edge = TileEdge::query()->create([
            'name' => 'Test Edge',
            'thickness' => '3/8',
            'color' => 'Grey',
            'unit_price' => 10.00,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $calc = app(TileQuotationCalculator::class);

        $cases = [
            [0.0, 0, 0.0],
            [1.0, 1, 10.0],
            [8.0, 1, 10.0],
            [8.01, 2, 20.0],
            [16.0, 2, 20.0],
            [16.1, 3, 30.0],
        ];

        foreach ($cases as [$lf, $pieces, $total]) {
            $result = $calc->compute(
                10,
                10,
                0,
                null,
                false,
                '1/4x1/4',
                null,
                0.125,
                null,
                0,
                100,
                false,
                'standard',
                0,
                false,
                true,
                (int) $edge->id,
                (float) $lf
            );

            $this->assertSame($pieces, $result['edge_pieces'], "LF {$lf} pieces");
            $this->assertEqualsWithDelta($total, $result['edge_total_price'], 0.01, "LF {$lf} total");
        }
    }

    public function test_edge_ignored_when_not_required(): void
    {
        $edge = TileEdge::query()->create([
            'name' => 'Test Edge',
            'unit_price' => 10.00,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $result = app(TileQuotationCalculator::class)->compute(
            10,
            10,
            0,
            null,
            false,
            '1/4x1/4',
            null,
            0.125,
            null,
            0,
            100,
            false,
            'standard',
            0,
            false,
            false,
            (int) $edge->id,
            20
        );

        $this->assertSame(0, $result['edge_pieces']);
        $this->assertEqualsWithDelta(0.0, $result['edge_total_price'], 0.01);
    }
}
