<?php

namespace Tests\Unit\Services;

use App\Models\MillworkSettings;
use App\Services\MillworkQuotationCalculator;
use Tests\TestCase;

class MillworkV2FuzzParityTest extends TestCase
{
    public function test_three_thousand_v2_quotes_never_skip_the_cut_plan(): void
    {
        $cases = json_decode(
            (string) shell_exec('php '.escapeshellarg(base_path('tests/bin/millwork-v2-fuzz-generate.php'))),
            true
        );
        $this->assertIsArray($cases);
        $this->assertCount(3000, $cases);

        $skipped = 0;
        foreach ($cases as $id => $inputs) {
            $calc = (new MillworkQuotationCalculator)->calculate($inputs, $this->settings(), 2);
            if (! empty($calc['cut_plan']['preview_skipped'])) {
                $skipped++;
                $this->fail($id.' skipped the v2 cut plan');
            }
        }
        $this->assertSame(0, $skipped);
    }

    private function settings(): MillworkSettings
    {
        $s = new MillworkSettings();
        $s->owner_hourly_rate = 85;
        $s->helper_hourly_rate = 45;
        $s->hours_per_day = 8;
        $s->productivity = MillworkSettings::defaultProductivity();
        $s->catalog = MillworkSettings::defaultCatalog();
        $s->cut_settings = MillworkSettings::defaultCutSettings();

        return $s;
    }
}
