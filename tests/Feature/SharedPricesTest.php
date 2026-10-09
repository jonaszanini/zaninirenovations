<?php

namespace Tests\Feature;

use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SharedPricesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_shared_prices_round_trip_with_grouped_money(): void
    {
        $this->actingAsAdmin();

        $crew = SharedCrewRate::current();

        $work = SharedWork::query()
            ->whereNotIn('unit', ['lf', 'sf'])
            ->first();

        if (! $work) {
            $work = SharedWork::query()->first();
            $work->unit = 'ea';
            $work->save();
        }

        $this->assertNotNull($work, 'At least one SharedWork row must exist');
        $this->assertFalse($work->isPricedPerArea(), 'Work must not be area-priced for this test');

        $testRateGrouped = '1,234.56';

        $payload = [
            'owner_hourly_rate' => $testRateGrouped,
            'helper_hourly_rate' => '85.00',
            'hours_per_day' => '8',
            'works' => [
                $work->id => [
                    'id' => $work->id,
                    'rate' => $testRateGrouped,
                    'flat_rate' => '5.6789',
                    'material_name' => 'Test Material',
                    'material_unit' => 'ea',
                    'material_unit_price' => '12.3456',
                ],
            ],
        ];

        $response = $this->put(route('admin.quotations.shared-prices.update'), $payload);
        $response->assertRedirect(route('admin.quotations.shared-prices.edit'));
        $response->assertSessionHas('status');

        $crew->refresh();
        $this->assertEquals(1234.56, $crew->owner_hourly_rate, 'Owner hourly rate should be stripped of commas and saved');
        $this->assertEquals(85.00, $crew->helper_hourly_rate);

        $work->refresh();
        $this->assertEqualsWithDelta(1234.56, $work->rate, 0.01, 'Work rate should be stripped of commas');
        $this->assertEquals('Test Material', $work->material_name);
        $this->assertEquals('ea', $work->material_unit);
        $this->assertEqualsWithDelta(12.3456, $work->material_unit_price, 0.0001);

        $editResponse = $this->get(route('admin.quotations.shared-prices.edit'));
        $editResponse->assertOk();
        $editResponse->assertSee('1234.56');
    }

    public function test_shared_prices_keeps_four_decimal_rate(): void
    {
        $this->actingAsAdmin();

        $work = SharedWork::query()
            ->whereNotIn('unit', ['lf', 'sf'])
            ->first();
        $this->assertNotNull($work);
        $this->assertFalse($work->isPricedPerArea());

        $payload = [
            'owner_hourly_rate' => '100.00',
            'helper_hourly_rate' => '50.00',
            'hours_per_day' => '8',
            'works' => [
                $work->id => [
                    'id' => $work->id,
                    'rate' => '24.8235',
                    'flat_rate' => '12.9000',
                    'material_name' => 'Precision trim',
                    'material_unit' => 'opening',
                    'material_unit_price' => '99999.9999',
                ],
            ],
        ];

        $this->put(route('admin.quotations.shared-prices.update'), $payload)
            ->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $work->refresh();
        $this->assertEqualsWithDelta(24.8235, (float) $work->rate, 0.0001);
        $this->assertEqualsWithDelta(12.9000, (float) $work->flat_rate, 0.0001);
        $this->assertEqualsWithDelta(99999.9999, (float) $work->material_unit_price, 0.0001);

        $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('24.8235', false)
            ->assertDontSee('value="24.82"', false);
    }

    public function test_shared_prices_accepts_plain_numeric_values(): void
    {
        $this->actingAsAdmin();

        $work = SharedWork::query()->first();
        $this->assertNotNull($work);

        $payload = [
            'owner_hourly_rate' => '100.00',
            'helper_hourly_rate' => '50.00',
            'hours_per_day' => '8',
            'works' => [
                $work->id => [
                    'id' => $work->id,
                    'rate' => '25.50',
                    'flat_rate' => '',
                    'material_name' => '',
                    'material_unit' => '',
                    'material_unit_price' => '',
                ],
            ],
        ];

        $response = $this->put(route('admin.quotations.shared-prices.update'), $payload);
        $response->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $crew = SharedCrewRate::current();
        $this->assertEquals(100.00, $crew->owner_hourly_rate);
        $this->assertEquals(50.00, $crew->helper_hourly_rate);
    }
}
