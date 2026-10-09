<?php

namespace Tests\Unit\Services;

use App\Models\Material;
use App\Services\MaterialMetadataPopulationService;
use App\Services\MaterialScraping\AmazonCaSerpApiProductFetcher;
use App\Services\MaterialScraping\HomeDepotCaLocalizedPriceFetcher;
use App\Services\MaterialScraping\HomeDepotCaProductParser;
use App\Services\MaterialScraping\MaterialPageFetcher;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use App\Services\MaterialScraping\ParsedProductData;
use Mockery;
use Tests\TestCase;

class MaterialPriceSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_non_syncable_material_is_skipped_without_metadata_population(): void
    {
        $material = Mockery::mock(Material::class)->makePartial();
        $material->shouldReceive('isAmazonSyncable')->andReturnFalse();
        $material->shouldReceive('isHomeDepotSyncable')->andReturnFalse();
        $material->shouldReceive('resolveAsin')->andReturnNull();
        $material->shouldReceive('hasProductUrl')->andReturnFalse();
        $material->shouldReceive('is_active')->andReturnTrue();
        $material->shouldReceive('save')->once();

        $population = Mockery::mock(MaterialMetadataPopulationService::class);
        $population->shouldNotReceive('applyToMaterial');

        $service = new MaterialPriceSyncService(
            Mockery::mock(MaterialPageFetcher::class),
            Mockery::mock(HomeDepotCaProductParser::class),
            Mockery::mock(HomeDepotCaLocalizedPriceFetcher::class),
            Mockery::mock(AmazonCaSerpApiProductFetcher::class),
            $population,
        );

        $result = $service->syncOne($material);

        $this->assertSame(Material::SCRAPE_SKIPPED, $result['status']);
    }

    public function test_dry_run_does_not_persist_or_populate_metadata(): void
    {
        $material = $this->homeDepotMaterial();
        $material->name = 'Old title';

        $parsed = new ParsedProductData(
            name: 'New title',
            priceCad: 9.99,
            sku: '1000789777',
            source: 'fbt-local',
        );

        $localizedFetcher = Mockery::mock(HomeDepotCaLocalizedPriceFetcher::class);
        $localizedFetcher->shouldReceive('configuredStoreId')->andReturn('7263');
        $localizedFetcher->shouldReceive('tryFetch')->andReturn($parsed);

        $population = Mockery::mock(MaterialMetadataPopulationService::class);
        $population->shouldNotReceive('applyToMaterial');

        $material->shouldNotReceive('save');

        $service = new MaterialPriceSyncService(
            Mockery::mock(MaterialPageFetcher::class),
            new HomeDepotCaProductParser($localizedFetcher),
            $localizedFetcher,
            Mockery::mock(AmazonCaSerpApiProductFetcher::class),
            $population,
        );

        $result = $service->syncOne($material, dryRun: true);

        $this->assertSame(Material::SCRAPE_OK, $result['status']);
        $this->assertSame('Old title', $material->name);
    }

    public function test_fetch_home_depot_sku_does_not_write_and_flags_unreachable(): void
    {
        $localizedFetcher = Mockery::mock(HomeDepotCaLocalizedPriceFetcher::class);
        $localizedFetcher->shouldReceive('configuredStoreId')->andReturn('7263');
        $localizedFetcher->shouldReceive('tryFetch')->andThrow(
            new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out')
        );

        $population = Mockery::mock(MaterialMetadataPopulationService::class);
        $population->shouldNotReceive('applyToMaterial');

        $service = new MaterialPriceSyncService(
            Mockery::mock(MaterialPageFetcher::class),
            new HomeDepotCaProductParser($localizedFetcher),
            $localizedFetcher,
            Mockery::mock(AmazonCaSerpApiProductFetcher::class),
            $population,
        );

        $result = $service->fetchHomeDepotSku('1001898921');

        $this->assertSame(Material::SCRAPE_FAILED, $result['status']);
        $this->assertTrue($result['unreachable']);
        $this->assertNull($result['parsed']);
    }

    private function homeDepotMaterial()
    {
        $material = Mockery::mock(Material::class)->makePartial();
        $material->id = 1;
        $material->name = 'Old title';
        $material->product_url = 'https://www.homedepot.ca/product/1000789777';
        $material->store_sku = '1000789777';
        $material->vendor_id = 1;
        $material->price_cad = 1.00;
        $material->is_active = true;

        $material->shouldReceive('isAmazonSyncable')->andReturnFalse();
        $material->shouldReceive('isHomeDepotSyncable')->andReturnTrue();
        $material->shouldReceive('isHomeDepotUrl')->andReturnTrue();
        $material->shouldReceive('hasProductUrl')->andReturnTrue();
        $material->shouldReceive('resolveAsin')->andReturnNull();

        return $material;
    }
}
