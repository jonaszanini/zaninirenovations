<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementClientIntakeMapper;
use App\Services\BasementQuotationCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class BasementClientIntakeMapperTest extends TestCase
{
    public function test_bathroom_preset_ignores_flooring_type_and_hourly_rates(): void
    {
        $existing = $this->bathroomInputs([
            'flooring_type' => 'lvp',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ]);

        $mapped = (new BasementClientIntakeMapper)->apply($this->bathroomAnswers([
            'flooring_type' => 'tile',
            'owner_hourly_rate' => 1,
            'helper_hourly_rate' => 2,
            'labor_overrides' => ['framing' => 99],
        ]), $existing);

        $this->assertSame('lvp', $mapped['inputs']['flooring_type']);
        $this->assertEquals(80, $mapped['inputs']['owner_hourly_rate']);
        $this->assertEquals(40, $mapped['inputs']['helper_hourly_rate']);
        $this->assertArrayNotHasKey('labor_overrides', $mapped['inputs']);
        $this->assertNotContains('flooring_type', $mapped['filled_keys']);
    }

    public function test_vanity_and_pedestal_both_posted_pedestal_wins(): void
    {
        $mapped = (new BasementClientIntakeMapper)->apply($this->bathroomAnswers([
            'sink' => null,
            'include_vanity' => '1',
            'include_pedestal_sink' => '1',
        ]), $this->bathroomInputs());

        $this->assertFalse((bool) $mapped['inputs']['include_vanity']);
        $this->assertTrue((bool) $mapped['inputs']['include_pedestal_sink']);
        $this->assertContains('include_pedestal_sink', $mapped['filled_keys']);
    }

    public function test_bath_sink_qty_maps_one_or_two(): void
    {
        $one = (new BasementClientIntakeMapper)->apply($this->bathroomAnswers([
            'sink' => 'vanity',
            'bath_sink_qty' => '1',
        ]), $this->bathroomInputs());
        $this->assertSame(1, $one['inputs']['bath_sink_qty']);

        $two = (new BasementClientIntakeMapper)->apply($this->bathroomAnswers([
            'sink' => 'pedestal',
            'bath_sink_qty' => '2',
        ]), $this->bathroomInputs());
        $this->assertSame(2, $two['inputs']['bath_sink_qty']);
        $this->assertTrue((bool) $two['inputs']['include_pedestal_sink']);
        $this->assertContains('bath_sink_qty', $two['filled_keys']);
    }

    public function test_client_provides_bath_items_zeros_those_materials(): void
    {
        $mapped = (new BasementClientIntakeMapper)->apply($this->bathroomAnswers([
            'sink' => 'vanity',
            'sink_provide' => 'client',
            'faucet_provide' => 'client',
            'include_toilet' => '1',
            'toilet_provide' => 'client',
        ]), $this->bathroomInputs());

        $this->assertEqualsCanonicalizing(
            ['vanity', 'faucet', 'toilet'],
            $mapped['inputs']['client_provides_bath_items']
        );
        $this->assertContains('client_provides_bath_items', $mapped['filled_keys']);

        $calc = (new BasementQuotationCalculator)->calculate($mapped['inputs'], $this->settings());
        foreach (['vanity', 'faucet', 'toilet'] as $key) {
            $row = collect($calc['materials'])->firstWhere('key', $key);
            $this->assertNotNull($row, $key.' material missing');
            $this->assertTrue($row['client_provided']);
            $this->assertSame(0.0, $row['amount']);
        }
    }

    public function test_client_filled_keys_drive_admin_badge_helper(): void
    {
        $mapped = (new BasementClientIntakeMapper)->apply($this->bathroomAnswers(), $this->bathroomInputs());
        $this->assertContains('include_vanity', $mapped['filled_keys']);
        $this->assertContains('area_sqft', $mapped['filled_keys']);
        $this->assertContains('bath_tile_areas', $mapped['filled_keys']);

        $shown = view('admin.quotations.basement._client_filled', [
            'key' => 'include_vanity',
            'filledKeys' => $mapped['filled_keys'],
        ])->render();
        $this->assertStringContainsString('br-client-filled', $shown);
        $this->assertStringContainsString('Filled from client form', $shown);

        $hidden = view('admin.quotations.basement._client_filled', [
            'key' => 'flooring_type',
            'filledKeys' => $mapped['filled_keys'],
        ])->render();
        $this->assertStringNotContainsString('br-client-filled', $hidden);
    }

    public function test_full_preset_marks_flooring_and_bathroom_only_does_not(): void
    {
        $full = (new BasementClientIntakeMapper)->apply([
            'area_entry_mode' => 'sqft',
            'area_sqft' => 800,
            'include_bathroom' => '0',
            'flooring_type' => 'laminate',
            'ceiling_type' => 'drop',
            'paint_colors' => '2',
        ], array_merge(BasementQuotationCalculator::defaultInputs(), [
            'scope_preset' => 'full',
        ]));

        $this->assertSame('laminate', $full['inputs']['flooring_type']);
        $this->assertContains('flooring_type', $full['filled_keys']);
        $this->assertContains('ceiling_type', $full['filled_keys']);
        $this->assertFalse((bool) $full['inputs']['include_bathroom']);

        $bath = (new BasementClientIntakeMapper)->apply($this->bathroomAnswers([
            'flooring_type' => 'carpet',
        ]), $this->bathroomInputs(['flooring_type' => 'lvp']));
        $this->assertNotContains('flooring_type', $bath['filled_keys']);
        $this->assertSame('lvp', $bath['inputs']['flooring_type']);
    }

    public function test_wall_preset_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new BasementClientIntakeMapper)->apply(
            ['area_sqft' => 10],
            array_merge(BasementQuotationCalculator::defaultInputs(), ['scope_preset' => 'wall'])
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bathroomInputs(array $overrides = []): array
    {
        return array_merge(BasementQuotationCalculator::defaultInputs(), [
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'area_sqft' => 80,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bathroomAnswers(array $overrides = []): array
    {
        $answers = [
            'area_entry_mode' => 'sqft',
            'area_sqft' => 80,
            'include_bathroom' => '1',
            'bath_type' => 'full',
            'sink' => 'vanity',
            'include_toilet' => '1',
            'include_shower' => '1',
            'include_tub' => '0',
            'include_exhaust_fan' => '1',
            'include_cleaning' => '1',
            'include_touchups' => '1',
            'tile_floor_sqft' => 40,
            'tile_floor_finish' => 'tile',
            'tile_shower_sqft' => 30,
            'tile_shower_finish' => 'tile',
        ];
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($answers[$key]);
                continue;
            }
            $answers[$key] = $value;
        }

        return $answers;
    }

    private function settings(): BasementSettings
    {
        return new BasementSettings([
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);
    }
}
