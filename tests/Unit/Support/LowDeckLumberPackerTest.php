<?php

namespace Tests\Unit\Support;

use App\Support\LowDeckLumberPacker;
use Tests\TestCase;

class LowDeckLumberPackerTest extends TestCase
{
    /** @return list<array{key: string, length_ft: float, price: float}> */
    private function stocks(): array
    {
        return [
            ['key' => 's8', 'length_ft' => 8.0, 'price' => 5.0],
            ['key' => 's10', 'length_ft' => 10.0, 'price' => 6.5],
            ['key' => 's12', 'length_ft' => 12.0, 'price' => 8.0],
        ];
    }

    public function test_two_eight_foot_pieces_use_two_eight_foot_stock(): void
    {
        $result = LowDeckLumberPacker::pack([8.0, 8.0], $this->stocks(), 0.0);

        $this->assertSame(2, $result['by_key']['s8']);
        $this->assertEqualsWithDelta(16.0, $result['total_stock_ft'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['waste_ft'], 0.001);
        $this->assertEqualsWithDelta(10.0, $result['total_price'], 0.001);
    }

    public function test_four_two_foot_posts_fit_one_eight_foot_stock(): void
    {
        $result = LowDeckLumberPacker::pack([2.0, 2.0, 2.0, 2.0], $this->stocks(), 0.0);

        $this->assertSame(1, $result['by_key']['s8']);
        $this->assertEqualsWithDelta(0.0, $result['waste_ft'], 0.001);
    }

    public function test_mixed_pack_prefers_zero_waste_combo(): void
    {
        // Two 10 ft pieces: two 10 ft boards (0 waste) beats any 12 ft mix.
        $result = LowDeckLumberPacker::pack([10.0, 10.0], $this->stocks(), 0.0);

        $this->assertSame(2, $result['by_key']['s10']);
        $this->assertEqualsWithDelta(0.0, $result['waste_ft'], 0.001);
    }

    public function test_chooses_cheapest_total_when_lengths_differ(): void
    {
        // One 6 ft + one 6 ft = 12 ft. Cheapest is a single 12 ft board ($8)
        // rather than two 8 ft boards ($10).
        $result = LowDeckLumberPacker::pack([6.0, 6.0], $this->stocks(), 0.0);

        $this->assertSame(1, $result['by_key']['s12']);
        $this->assertEqualsWithDelta(8.0, $result['total_price'], 0.001);
    }

    public function test_empty_input_returns_zero_result(): void
    {
        $result = LowDeckLumberPacker::pack([], $this->stocks(), 0.0);

        $this->assertSame([], $result['by_key']);
        $this->assertEqualsWithDelta(0.0, $result['total_price'], 0.001);
    }
}
