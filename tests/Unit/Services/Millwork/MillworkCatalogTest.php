<?php

namespace Tests\Unit\Services\Millwork;

use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkCatalog;
use Tests\TestCase;

class MillworkCatalogTest extends TestCase
{
    public function test_option_label_adds_edge_banding_coverage_and_hinge_price(): void
    {
        $this->assertSame(
            'Maple iron-on (50 LF roll)',
            MillworkCatalog::optionLabel(MillworkSettings::SLOT_EDGE_BANDING, [
                'name' => 'Maple iron-on',
                'coverage_lf' => 50,
            ])
        );
        $this->assertStringContainsString(
            '—',
            MillworkCatalog::optionLabel(MillworkSettings::SLOT_HINGES, [
                'name' => 'Soft-close',
                'unit_price' => 3.8,
            ])
        );
    }

    public function test_merge_snapshot_keeps_saved_price_over_live(): void
    {
        $live = [
            MillworkSettings::SLOT_WOOD_SHEETS => [[
                'id' => 'ws_ply_34_cab_48x96',
                'name' => 'Live sheet',
                'unit' => 'sheet',
                'unit_price' => 200,
                'thickness_in' => 0.75,
                'width_in' => 48,
                'length_in' => 96,
            ]],
        ];
        $snap = [
            MillworkSettings::SLOT_WOOD_SHEETS => [[
                'id' => 'ws_ply_34_cab_48x96',
                'name' => 'Saved sheet',
                'unit' => 'sheet',
                'unit_price' => 72,
                'thickness_in' => 0.75,
                'width_in' => 48,
                'length_in' => 96,
            ]],
        ];

        $merged = MillworkCatalog::mergeSnapshot($live, $snap);
        $row = $merged[MillworkSettings::SLOT_WOOD_SHEETS][0];
        $this->assertSame('Saved sheet', $row['name']);
        $this->assertEquals(72.0, (float) $row['unit_price']);
    }

    public function test_snapshot_from_inputs_only_keeps_selected_ids(): void
    {
        $catalog = (new MillworkSettings)->resolvedCatalog();
        $sheetId = $catalog[MillworkSettings::SLOT_WOOD_SHEETS][0]['id'];
        $snap = MillworkCatalog::snapshotFromInputs([
            'sheet_id' => $sheetId,
            'trim_id' => 'missing',
        ], $catalog);

        $this->assertCount(1, $snap[MillworkSettings::SLOT_WOOD_SHEETS]);
        $this->assertSame($sheetId, $snap[MillworkSettings::SLOT_WOOD_SHEETS][0]['id']);
        $this->assertArrayNotHasKey(MillworkSettings::SLOT_TRIM, $snap);
    }

    public function test_hidden_rows_are_skipped_from_live_merge(): void
    {
        $live = [
            MillworkSettings::SLOT_TRIM => [[
                'id' => 'old_trim',
                'name' => 'Old trim',
                'unit' => 'lf',
                'unit_price' => 2,
                'deleted_at' => '2026-10-09T00:00:00+00:00',
            ]],
        ];
        $merged = MillworkCatalog::mergeSnapshot($live, []);
        $this->assertSame([], $merged[MillworkSettings::SLOT_TRIM]);
    }
}
