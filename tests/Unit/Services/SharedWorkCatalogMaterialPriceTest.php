<?php

namespace Tests\Unit\Services;

use App\Models\BasementQuotation;
use App\Models\Material;
use App\Models\SharedWork;
use App\Models\TrimmingSettings;
use App\Services\BasementQuotationCalculator;
use App\Services\SharedWorkCatalog;
use App\Services\TrimmingQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SharedWorkCatalogMaterialPriceTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    private const CATALOG_PRICE = 12.34;

    private const CATALOG_PRICE_AFTER = 88.88;

    private ?int $originalShoeCatalogMaterialId = null;

    private mixed $originalShoeMaterialUnitPrice = null;

    protected function setUp(): void
    {
        parent::setUp();

        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $this->originalShoeCatalogMaterialId = $work->catalog_material_id;
        $this->originalShoeMaterialUnitPrice = $work->material_unit_price;
    }

    protected function tearDown(): void
    {
        $work = SharedWork::query()->where('key', 'shoe_install')->first();
        if ($work !== null) {
            $work->catalog_material_id = $this->originalShoeCatalogMaterialId;
            $work->material_unit_price = $this->originalShoeMaterialUnitPrice;
            $work->save();
            SharedWorkCatalog::forgetCache();
        }

        parent::tearDown();
    }

    public function test_linked_shared_work_uses_live_catalog_price_after_catalog_changes(): void
    {
        $material = $this->createCatalogMaterial(self::CATALOG_PRICE);

        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $storedFallback = 3.25;
        $work->catalog_material_id = $material->id;
        $work->material_unit_price = $storedFallback;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $def = SharedWorkCatalog::definition('shoe_install');
        $slot = SharedWorkCatalog::materialSlotFromWork($work, $def);
        $this->assertEqualsWithDelta(self::CATALOG_PRICE, (float) $slot['unit_price'], 0.0001);

        $material->price_cad = self::CATALOG_PRICE_AFTER;
        $material->save();
        SharedWorkCatalog::forgetCache();

        $slotAfter = SharedWorkCatalog::materialSlotFromWork($work->fresh(), $def);
        $this->assertEqualsWithDelta(self::CATALOG_PRICE_AFTER, (float) $slotAfter['unit_price'], 0.0001);
        $this->assertEqualsWithDelta($storedFallback, (float) $work->fresh()->material_unit_price, 0.0001);
    }

    public function test_unlinked_shared_work_falls_back_to_stored_material_unit_price(): void
    {
        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $work->catalog_material_id = null;
        $work->material_unit_price = 6.50;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $def = SharedWorkCatalog::definition('shoe_install');
        $slot = SharedWorkCatalog::materialSlotFromWork($work, $def);
        $this->assertNull($slot['catalog_material_id']);
        $this->assertEqualsWithDelta(6.50, (float) $slot['unit_price'], 0.0001);
    }

    public function test_stored_zero_material_unit_price_means_no_material_not_definition_fallback(): void
    {
        $work = SharedWork::query()->where('key', 'underlayment')->firstOrFail();
        $def = SharedWorkCatalog::definition('underlayment');
        $this->assertNotNull($def);
        $fallback = (float) ($def['material']['unit_price'] ?? 0);
        $this->assertGreaterThan(0, $fallback);

        $work->catalog_material_id = null;
        $work->material_unit_price = 0;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $slot = SharedWorkCatalog::materialSlotFromWork($work, $def);
        $this->assertSame(0.0, (float) $slot['unit_price']);

        $work->material_unit_price = null;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $slotNull = SharedWorkCatalog::materialSlotFromWork($work->fresh(), $def);
        $this->assertEqualsWithDelta($fallback, (float) $slotNull['unit_price'], 0.0001);
    }

    public function test_trimming_shoe_material_uses_live_catalog_unit_price(): void
    {
        $material = $this->createCatalogMaterial(18.50, 'SP-TRIM-SHOE');

        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $work->catalog_material_id = $material->id;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $calc = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 40,
            'include_shoe' => true,
            'include_baseboard' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], TrimmingSettings::current());

        $shoe = collect($calc['materials'])->firstWhere('key', 'shoe');
        $this->assertNotNull($shoe);
        $this->assertEqualsWithDelta(18.50, (float) $shoe['unit_price'], 0.01);
        $this->assertEqualsWithDelta(111.0, (float) $shoe['amount'], 0.01);

        $material->price_cad = 22.00;
        $material->save();
        SharedWorkCatalog::forgetCache();

        $calcAfter = (new TrimmingQuotationCalculator)->calculate([
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 40,
            'include_shoe' => true,
            'include_baseboard' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], TrimmingSettings::current());

        $shoeAfter = collect($calcAfter['materials'])->firstWhere('key', 'shoe');
        $this->assertEqualsWithDelta(22.00, (float) $shoeAfter['unit_price'], 0.01);
        $this->assertEqualsWithDelta(132.0, (float) $shoeAfter['amount'], 0.01);
    }

    public function test_saved_basement_quote_freezes_totals_new_preview_uses_live_catalog(): void
    {
        $this->actingAsAdmin();

        $material = $this->createCatalogMaterial(self::CATALOG_PRICE, 'SP-SHOE-SNAP');

        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $work->catalog_material_id = $material->id;
        $work->material_unit_price = 4.00;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $payload = $this->basementShoePayload();

        $preview = $this->post(route('admin.quotations.basement.preview'), $payload)->assertOk();
        $baselineMaterials = (float) $preview->json('summary.materials_pre_hst');
        $baselineGrand = (float) $preview->json('summary.grand_total');
        $this->assertGreaterThan(0, $baselineMaterials);
        $this->assertGreaterThan(0, $baselineGrand);

        $this->post(route('admin.quotations.basement.store'), array_merge($payload, [
            'save_intent' => 'publish',
            'client_name' => 'Catalog snapshot client QA',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Catalog snapshot client QA')->firstOrFail();
        $this->assertEqualsWithDelta($baselineGrand, (float) $quote->final_quote_total, 0.01);
        $calcBefore = is_array($quote->calculation) ? $quote->calculation : [];
        $this->assertEqualsWithDelta(
            $baselineMaterials,
            (float) ($calcBefore['summary']['materials_pre_hst'] ?? 0),
            0.01
        );

        $material->price_cad = self::CATALOG_PRICE_AFTER;
        $material->save();
        SharedWorkCatalog::forgetCache();

        $quote->refresh();
        $this->assertEqualsWithDelta($baselineGrand, (float) $quote->final_quote_total, 0.01);
        $calcAfter = is_array($quote->calculation) ? $quote->calculation : [];
        $this->assertEqualsWithDelta(
            $baselineMaterials,
            (float) ($calcAfter['summary']['materials_pre_hst'] ?? 0),
            0.01
        );

        $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->assertSee('name="client_name"', false)
            ->assertSee('Catalog snapshot client QA', false);

        $frozenGrand = (float) (($calcAfter['summary']['grand_total'] ?? 0));
        $this->assertEqualsWithDelta($baselineGrand, $frozenGrand, 0.01);

        $livePreview = $this->post(route('admin.quotations.basement.preview'), $payload)->assertOk();
        $liveMaterials = (float) $livePreview->json('summary.materials_pre_hst');
        $this->assertGreaterThan($baselineMaterials, $liveMaterials);
    }

  /**
     * @return array<string, mixed>
     */
    private function basementShoePayload(): array
    {
        return [
            'client_name' => 'Catalog snapshot client QA',
            'scope_preset' => 'custom',
            'enabled_sections' => ['overview', 'doors_trim'],
            'area_sqft' => 200,
            'ceiling_height_ft' => 8,
            'room_count' => 1,
            'include_shoe' => 1,
            'shoe_lf' => 24,
            'baseboard_lf' => 0,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'insulate_walls' => 0,
            'flooring_type' => 'none',
            'include_cleaning' => 0,
            'include_touchups' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'detector_qty' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'payment_installments' => 3,
            'ask_reply_to_accept' => '0',
        ];
    }

    private function createCatalogMaterial(float $price, string $sku = 'SP-CAT-LIVE'): Material
    {
        return Material::query()->create([
            'name' => 'Catalog QA material '.$sku,
            'store_sku' => $sku,
            'price_cad' => $price,
            'is_active' => true,
            'unit' => 'each',
        ]);
    }
}
