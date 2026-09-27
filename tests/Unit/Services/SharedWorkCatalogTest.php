<?php

namespace Tests\Unit\Services;

use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharedWorkCatalogTest extends TestCase
{
    public function test_definitions_include_shoe_and_baseboard(): void
    {
        $defs = SharedWorkCatalog::definitions();

        $this->assertArrayHasKey('shoe_install', $defs);
        $this->assertArrayHasKey('shoe_caulk_paint', $defs);
        $this->assertArrayHasKey('baseboard', $defs);
        $this->assertArrayHasKey('door_install', $defs);
        $this->assertSame('lf', $defs['shoe_install']['unit']);
        $this->assertSame(SharedWork::KIND_FLAT, $defs['shoe_install']['labor_kind']);
        $this->assertEqualsWithDelta(21.7949, $defs['shoe_install']['rate'], 0.01);
        $this->assertEqualsWithDelta(3.90, $defs['shoe_install']['flat_rate'], 0.01);
        $this->assertArrayHasKey('pocket_door', $defs);
        $this->assertArrayHasKey('stain_trim', $defs);
        $this->assertArrayHasKey('wainscot_shaker', $defs);
        $this->assertEqualsWithDelta(19.90, $defs['box_moulding']['flat_rate'], 0.01);
        $this->assertSame(SharedWork::KIND_FLAT, $defs['baseboard']['labor_kind']);
        $this->assertSame(SharedWork::KIND_FLAT, $defs['casing_lf']['labor_kind']);
        $this->assertGreaterThan(0, $defs['casing_lf']['flat_rate']);
        $this->assertContains('flooring', $defs['shoe_install']['consumers']);
        $this->assertContains('trimming', $defs['shoe_install']['consumers']);
        $this->assertContains('flooring', $defs['baseboard']['consumers']);
        $this->assertArrayHasKey('baseboard_caulk_paint', $defs);
        $this->assertEqualsWithDelta(20.0, $defs['baseboard']['rate'], 0.01);
        $this->assertSame(SharedWork::KIND_FLAT, $defs['flooring_install']['labor_kind']);
        $this->assertSame('sf', $defs['flooring_install']['unit']);
        $this->assertEqualsWithDelta(4.25, $defs['flooring_install']['flat_rate'], 0.01);
        $this->assertSame(SharedWork::KIND_FLAT, $defs['underlayment']['labor_kind']);
        $this->assertGreaterThan(0, $defs['underlayment']['flat_rate']);
        $this->assertSame(SharedWork::KIND_FLAT, $defs['paint_walls']['labor_kind']);
        $this->assertSame(SharedWork::KIND_FLAT, $defs['drywall_hang']['labor_kind']);
    }

    public function test_works_for_consumer_lists_flooring_keys(): void
    {
        $keys = SharedWorkCatalog::worksForConsumer('flooring');

        $this->assertContains('shoe_install', $keys);
        $this->assertContains('shoe_caulk_paint', $keys);
        $this->assertContains('remove_floor', $keys);
        $this->assertContains('baseboard', $keys);
        $this->assertContains('baseboard_caulk_paint', $keys);
    }

    public function test_overlay_and_flat_rate_use_library_when_available(): void
    {
        if (! $this->libraryAvailable()) {
            $this->markTestSkipped('Shared price library is not available.');
        }

        $work = SharedWork::query()->where('key', 'baseboard')->firstOrFail();
        $originalRate = (float) $work->rate;
        $originalFlat = $work->flat_rate;
        $work->flat_rate = 5.00;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $overlay = SharedWorkCatalog::overlayProductivity('trimming', [
            'baseboard_lf_per_hr' => 20,
            'shoe_lf_per_hr' => 30,
        ], 100.0);
        $this->assertEqualsWithDelta(20.0, $overlay['baseboard_lf_per_hr'], 0.01);

        $shoe = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $originalShoeFlat = $shoe->flat_rate;
        $shoe->flat_rate = 4.25;
        $shoe->save();
        SharedWorkCatalog::forgetCache();
        $this->assertEqualsWithDelta(4.25, SharedWorkCatalog::flatRate('shoe_install', 3.50), 0.01);
        $this->assertSame('$4.25 / lf', SharedWorkCatalog::rateLine('shoe_install'));

        $work->rate = $originalRate;
        $work->flat_rate = $originalFlat;
        $work->save();
        $shoe->flat_rate = $originalShoeFlat;
        $shoe->save();
        SharedWorkCatalog::forgetCache();
    }

    private function libraryAvailable(): bool
    {
        try {
            return Schema::hasTable('shared_works')
                && SharedWork::query()->where('key', 'shoe_install')->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
