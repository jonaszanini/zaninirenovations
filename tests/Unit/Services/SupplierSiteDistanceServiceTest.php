<?php

namespace Tests\Unit\Services;

use App\Models\CompanySetting;
use App\Services\GoogleMapsService;
use App\Services\SupplierSiteDistanceService;
use Mockery;
use Tests\IntegrationTestCase;

class SupplierSiteDistanceServiceTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pick_closest_address_uses_shortest_distance(): void
    {
        CompanySetting::current()->forceFill([
            'home_address_latitude' => 45.0,
            'home_address_longitude' => -76.0,
        ])->save();

        $maps = Mockery::mock(GoogleMapsService::class);
        $maps->shouldReceive('isDistanceMatrixConfigured')->andReturn(true);
        $maps->shouldReceive('drivingDistanceToAddress')
            ->with(45.0, -76.0, 'Far Address, ON')
            ->andReturn([
                'ok' => true,
                'distance_km' => 42.0,
                'duration_seconds' => 2400,
                'duration_text' => '40 mins',
                'message' => null,
            ]);
        $maps->shouldReceive('drivingDistanceToAddress')
            ->with(45.0, -76.0, 'Near Address, ON')
            ->andReturn([
                'ok' => true,
                'distance_km' => 8.5,
                'duration_seconds' => 720,
                'duration_text' => '12 mins',
                'message' => null,
            ]);

        $service = new SupplierSiteDistanceService($maps);
        $best = $service->pickClosestAddress(['Far Address, ON', 'Near Address, ON']);

        $this->assertNotNull($best);
        $this->assertSame('Near Address, ON', $best['address']);
        $this->assertSame(8.5, $best['distance_km']);
        $this->assertSame(12, $best['drive_minutes']);
    }
}
