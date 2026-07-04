<?php

namespace Tests\Unit\Models;

use App\Models\SupplierTag;
use Tests\TestCase;

class SupplierTagTest extends TestCase
{
    public function test_parse_names_from_input_deduplicates_case_insensitively(): void
    {
        $names = SupplierTag::parseNamesFromInput('Bin Rental, crushed stone, bin rental, bedding sand');

        $this->assertSame(['Bin Rental', 'crushed stone', 'bedding sand'], $names);
    }

    public function test_parse_names_from_input_returns_empty_for_blank(): void
    {
        $this->assertSame([], SupplierTag::parseNamesFromInput(null));
        $this->assertSame([], SupplierTag::parseNamesFromInput('   '));
    }
}
