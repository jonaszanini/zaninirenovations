<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Models\SharedWork;
use App\Services\BasementQuotationCalculator;
use App\Services\SharedWorkCatalog;
use Tests\IntegrationTestCase;

/**
 * Overlay hours must come from seeded library flats, not defaultProductivity
 * and not from reading the same resolvedProductivity() path the calculator uses.
 */
class BasementQuotationProductivityOverlayTest extends IntegrationTestCase
{
    private const OWNER_HOURLY = 85.0;

    private const SHOE_FLAT = 3.90;

    private const TILE_FLAT = 12.0;

    private const FLOOR_FLAT = 4.25;

    private const UNDERLAY_FLAT = 2.125;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedKnownLibraryFixture();
    }

    public function test_shoe_forty_lf_is_1_84_hours_from_owner_over_flat_not_thirty_lf_per_hr(): void
    {
        $fromFixture = round(40 / (self::OWNER_HOURLY / self::SHOE_FLAT), 2);
        $this->assertEqualsWithDelta(1.84, $fromFixture, 0.001);

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 40,
            'include_shoe' => true,
            'shoe_lf' => 40,
            'door_qty' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $labor = collect($calc['labor'])->firstWhere('key', 'trim_shoe');
        $this->assertNotNull($labor);
        $this->assertEqualsWithDelta(1.84, (float) $labor['hours'], 0.01);
        $this->assertNotEquals(round(40 / 30, 2), (float) $labor['hours']);
    }

    public function test_bathroom_tile_vinyl_underlayment_hours_match_seeded_flats_not_fallback_rates(): void
    {
        $this->assertEqualsWithDelta(9.04, round(64 / (self::OWNER_HOURLY / self::TILE_FLAT), 2), 0.001);
        $this->assertEqualsWithDelta(2.00, round(40 / (self::OWNER_HOURLY / self::FLOOR_FLAT), 2), 0.001);
        $this->assertEqualsWithDelta(1.00, round(40 / (self::OWNER_HOURLY / self::UNDERLAY_FLAT), 2), 0.001);

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 80,
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'detector_qty' => 0,
            'bath_tile_areas' => [
                ['label' => 'Floor', 'sqft' => 40, 'finish' => 'vinyl'],
                ['label' => 'Shower', 'sqft' => 64, 'finish' => 'tile'],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $tileLabor = collect($calc['labor'])->firstWhere('key', 'bath_tile');
        $this->assertNotNull($tileLabor);
        $this->assertEqualsWithDelta(9.04, (float) $tileLabor['hours'], 0.01);
        $this->assertNotEquals(round(64 / 12, 2), (float) $tileLabor['hours']);

        $vinylLabor = collect($calc['labor'])->firstWhere('key', 'bath_vinyl');
        $this->assertNotNull($vinylLabor);
        $this->assertEqualsWithDelta(2.00, (float) $vinylLabor['hours'], 0.01);
        $this->assertNotEquals(round(40 / 25, 2), (float) $vinylLabor['hours']);

        $underlayLabor = collect($calc['labor'])->firstWhere('key', 'bath_vinyl_underlay');
        $this->assertNotNull($underlayLabor);
        $this->assertEqualsWithDelta(1.00, (float) $underlayLabor['hours'], 0.01);
        $this->assertNotEquals(round(40 / 80, 2), (float) $underlayLabor['hours']);
    }

    private function seedKnownLibraryFixture(): void
    {
        $this->forceWork('shoe_install', self::SHOE_FLAT, 30, 'lf');
        $this->forceWork('tile_install', self::TILE_FLAT, 12, 'sf');
        $this->forceWork('flooring_install', self::FLOOR_FLAT, 25, 'sf');
        $this->forceWork('underlayment', self::UNDERLAY_FLAT, 80, 'sf');
        SharedWorkCatalog::forgetCache();
    }

    private function forceWork(string $key, float $flat, float $rate, string $unit): void
    {
        $work = SharedWork::query()->where('key', $key)->first();
        $this->assertNotNull($work, $key.' must be seeded by migrations');
        $work->flat_rate = $flat;
        $work->rate = $rate;
        $work->unit = $unit;
        $work->save();
    }

    private function settings(): BasementSettings
    {
        return new BasementSettings([
            'owner_hourly_rate' => self::OWNER_HOURLY,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);
    }
}
