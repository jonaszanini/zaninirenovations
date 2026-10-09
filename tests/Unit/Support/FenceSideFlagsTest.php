<?php

namespace Tests\Unit\Support;

use App\Support\FenceSideFlags;
use Tests\TestCase;

class FenceSideFlagsTest extends TestCase
{
    public function test_string_zero_is_not_ticked(): void
    {
        $this->assertFalse(FenceSideFlags::isOn('0'));
        $this->assertFalse(FenceSideFlags::isOn(0));
        $this->assertFalse(FenceSideFlags::isOn(false));
        $this->assertFalse(FenceSideFlags::isOn(null));
        $this->assertFalse(FenceSideFlags::isOn(''));
        $this->assertFalse(FenceSideFlags::isOn('false'));
    }

    public function test_one_and_true_are_ticked(): void
    {
        $this->assertTrue(FenceSideFlags::isOn('1'));
        $this->assertTrue(FenceSideFlags::isOn(1));
        $this->assertTrue(FenceSideFlags::isOn(true));
    }

    public function test_old_input_strings_restore_real_booleans(): void
    {
        $sides = FenceSideFlags::normaliseSides([
            [
                'length_ft' => '20.25',
                'already_have_post_left' => '0',
                'already_have_post_right' => '0',
                'gate_goes_here' => '1',
                'connected_to_previous' => '0',
            ],
            [
                'length_ft' => '16',
                'already_have_post_left' => '1',
                'already_have_post_right' => 0,
                'gate_goes_here' => '0',
            ],
        ]);

        $this->assertFalse($sides[0]['already_have_post_left']);
        $this->assertFalse($sides[0]['already_have_post_right']);
        $this->assertTrue($sides[0]['gate_goes_here']);
        $this->assertFalse($sides[0]['connected_to_previous']);

        $this->assertTrue($sides[1]['already_have_post_left']);
        $this->assertFalse($sides[1]['already_have_post_right']);
        $this->assertFalse($sides[1]['gate_goes_here']);
        $this->assertTrue($sides[1]['connected_to_previous']);
    }
}
