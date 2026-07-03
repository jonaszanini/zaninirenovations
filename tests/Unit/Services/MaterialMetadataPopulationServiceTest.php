<?php

namespace Tests\Unit\Services;

use App\Models\Material;
use App\Services\MaterialMetadataEnricher;
use App\Services\MaterialMetadataPopulationService;
use App\Services\MaterialShortNameAiService;
use PHPUnit\Framework\TestCase;

class MaterialMetadataPopulationServiceTest extends TestCase
{
    public function test_suggested_changes_fill_blank_fields_only(): void
    {
        $material = new Material([
            'id' => 1,
            'name' => "1 x 6 x 8' Premium Wood Fence Board (Above Ground Use Only)",
        ]);

        $shortNameAi = $this->createMock(MaterialShortNameAiService::class);
        $shortNameAi->method('generateShortNames')->willReturn([
            ['id' => 1, 'short_name' => "8' Fence Board"],
        ]);

        $service = new MaterialMetadataPopulationService(new MaterialMetadataEnricher, $shortNameAi);
        $changes = $service->suggestedChanges($material);

        $this->assertSame("8' Fence Board", $changes['short_name']);
        $this->assertSame(3, $changes['category_id']);
        $this->assertSame('each', $changes['unit']);
    }

    public function test_suggested_changes_skip_short_name_when_already_set(): void
    {
        $material = new Material([
            'id' => 1,
            'name' => "1 x 6 x 8' Premium Wood Fence Board (Above Ground Use Only)",
            'short_name' => 'Custom label',
        ]);

        $shortNameAi = $this->createMock(MaterialShortNameAiService::class);
        $shortNameAi->expects($this->never())->method('generateShortNames');

        $service = new MaterialMetadataPopulationService(new MaterialMetadataEnricher, $shortNameAi);
        $changes = $service->suggestedChanges($material);

        $this->assertArrayNotHasKey('short_name', $changes);
    }

    public function test_only_short_name_mode_skips_category_and_unit(): void
    {
        $material = new Material([
            'id' => 1,
            'name' => "1 x 6 x 8' Premium Wood Fence Board (Above Ground Use Only)",
        ]);

        $shortNameAi = $this->createMock(MaterialShortNameAiService::class);
        $shortNameAi->method('generateShortNames')->willReturn([
            ['id' => 1, 'short_name' => "8' Fence Board"],
        ]);

        $service = new MaterialMetadataPopulationService(new MaterialMetadataEnricher, $shortNameAi);
        $changes = $service->suggestedChanges($material, onlyShortName: true);

        $this->assertSame("8' Fence Board", $changes['short_name']);
        $this->assertArrayNotHasKey('category_id', $changes);
        $this->assertArrayNotHasKey('unit', $changes);
    }

    public function test_only_short_name_mode_skips_materials_that_already_have_short_name(): void
    {
        $material = new Material([
            'id' => 1,
            'name' => "1 x 6 x 8' Premium Wood Fence Board (Above Ground Use Only)",
            'short_name' => 'Custom label',
        ]);

        $shortNameAi = $this->createMock(MaterialShortNameAiService::class);
        $shortNameAi->expects($this->never())->method('generateShortNames');

        $service = new MaterialMetadataPopulationService(new MaterialMetadataEnricher, $shortNameAi);
        $changes = $service->suggestedChanges($material, onlyShortName: true);

        $this->assertSame([], $changes);
    }
}
