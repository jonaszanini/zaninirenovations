<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\MaterialScraping\SteppedMaterialPriceSync;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class SteppedMaterialPriceSyncTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_start_rejects_empty_material_list(): void
    {
        $request = $this->requestAsUser(9);

        $service = new SteppedMaterialPriceSync(
            Mockery::mock(MaterialPriceSyncService::class),
            Mockery::mock(HomeDepotCaLocalizedPriceFetcher::class),
        );

        $response = $service->start($request, [], 'test_sync.', 'Nothing to sync.');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Nothing to sync.', $response->getData(true)['message']);
    }

    public function test_start_creates_session_token_for_unique_ids(): void
    {
        $request = $this->requestAsUser(3);

        $fetcher = Mockery::mock(HomeDepotCaLocalizedPriceFetcher::class);
        $fetcher->shouldReceive('usesSerpApi')->once()->andReturnTrue();

        $service = new SteppedMaterialPriceSync(
            Mockery::mock(MaterialPriceSyncService::class),
            $fetcher,
        );

        $response = $service->start($request, [10, 10, 20], 'test_sync.', 'Nothing to sync.');
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($data['done']);
        $this->assertSame(2, $data['phase_total']);
        $this->assertSame(2, $data['remaining']);
        $this->assertNotEmpty($data['token']);
        $this->assertStringContainsString('SerpApi', $data['message']);
        $this->assertTrue(session()->has('test_sync.'.$data['token']));
    }

    private function requestAsUser(int $userId): Request
    {
        $user = new User;
        $user->id = $userId;

        $request = Request::create('/sync/start', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
