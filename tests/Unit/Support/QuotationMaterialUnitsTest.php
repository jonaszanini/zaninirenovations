<?php

namespace Tests\Unit\Support;

use App\Models\BasementSettings;
use App\Models\ShedSettings;
use App\Models\TrimmingSettings;
use App\Support\QuotationMaterialUnits;
use Tests\TestCase;

class QuotationMaterialUnitsTest extends TestCase
{
    public function test_options_cover_basement_shed_and_trimming_defaults(): void
    {
        $options = QuotationMaterialUnits::options();
        $classes = [BasementSettings::class, ShedSettings::class, TrimmingSettings::class];

        foreach ($classes as $class) {
            foreach ($class::defaultMaterials() as $key => $row) {
                $this->assertArrayHasKey(
                    $row['unit'],
                    $options,
                    $class.' '.$key.' uses unknown unit '.$row['unit']
                );
            }
        }
    }

    public function test_unknown_current_unit_is_kept_in_the_list(): void
    {
        $options = QuotationMaterialUnits::optionsWith('sq');

        $this->assertArrayHasKey('sq', $options);
        $this->assertArrayHasKey('each', $options);
    }
}
