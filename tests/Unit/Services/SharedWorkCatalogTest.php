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
        $this->assertEqualsWithDelta(30.0, $defs['shoe_install']['rate'], 0.01);
        $this->assertEqualsWithDelta(3.50, $defs['shoe_install']['flat_rate'], 0.01);
        $this->assertContains('flooring', $defs['shoe_install']['consumers']);
        $this->assertContains('trimming', $defs['shoe_install']['consumers']);
        $this->assertEqualsWithDelta(20.0, $defs['baseboard']['rate'], 0.01);
    }

    public function test_works_for_consumer_lists_flooring_keys(): void
    {
        $keys = SharedWorkCatalog::worksForConsumer('flooring');

        $this->assertContains('shoe_install', $keys);
        $this->assertContains('shoe_caulk_paint', $keys);
        $this->assertContains('remove_floor', $keys);
    }

    public function test_overlay_and_flat_rate_use_library_when_available(): void
    {
        if (! $this->libraryAvailable()) {
            $this->markTestSkipped('Shared price library is not available.');
        }

        $work = SharedWork::query()->where('key', 'baseboard')->firstOrFail();
        $original = (float) $work->rate;
        $work->rate = 25;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $overlay = SharedWorkCatalog::overlayProductivity('trimming', [
            'baseboard_lf_per_hr' => 20,
            'shoe_lf_per_hr' => 30,
        ]);
        $this->assertEqualsWithDelta(25.0, $overlay['baseboard_lf_per_hr'], 0.01);

        $shoe = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $shoe->flat_rate = 4.25;
        $shoe->save();
        SharedWorkCatalog::forgetCache();
        $this->assertEqualsWithDelta(4.25, SharedWorkCatalog::flatRate('shoe_install', 3.50), 0.01);

        $work->rate = $original;
        $work->save();
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
