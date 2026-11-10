<?php

namespace Tests\Unit\Services;

use App\Models\BasementQuotation;
use App\Models\Material;
use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SharedWorkCatalogMaterialPriceTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_linked_shared_work_uses_live_catalog_price_after_catalog_changes(): void
    {
        $material = Material::query()->create([
            'name' => 'Live catalog shoe moulding',
            'store_sku' => 'SP-CAT-LIVE-1',
            'price_cad' => 10.00,
            'is_active' => true,
            'unit' => 'each',
        ]);

        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $storedFallback = 3.25;
        $work->catalog_material_id = $material->id;
        $work->material_unit_price = $storedFallback;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $def = SharedWorkCatalog::definition('shoe_install');
        $this->assertNotNull($def);
        $slot = SharedWorkCatalog::materialSlotFromWork($work, $def);
        $this->assertEqualsWithDelta(10.0, (float) $slot['unit_price'], 0.0001);
        $this->assertSame($material->id, (int) $slot['catalog_material_id']);

        $material->price_cad = 14.75;
        $material->save();
        SharedWorkCatalog::forgetCache();

        $slotAfter = SharedWorkCatalog::materialSlotFromWork($work->fresh(), $def);
        $this->assertEqualsWithDelta(14.75, (float) $slotAfter['unit_price'], 0.0001);
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

    public function test_saved_basement_quotation_totals_unchanged_when_live_catalog_price_changes(): void
    {
        $this->actingAsAdmin();

        $material = Material::query()->create([
            'name' => 'Basement shoe catalog item',
            'store_sku' => 'SP-SHOE-SNAP',
            'price_cad' => 9.99,
            'is_active' => true,
            'unit' => 'each',
        ]);

        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $work->catalog_material_id = $material->id;
        $work->material_unit_price = 4.00;
        $work->save();
        SharedWorkCatalog::forgetCache();

        $payload = [
            'save_intent' => 'publish',
            'client_name' => 'Catalog snapshot client',
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

        $response = $this->post(route('admin.quotations.basement.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Catalog snapshot client')->firstOrFail();
        $calcBefore = is_array($quote->calculation) ? $quote->calculation : [];
        $summaryBefore = $calcBefore['summary'] ?? [];
        $this->assertArrayHasKey('materials_pre_hst', $summaryBefore);

        $material->price_cad = 99.99;
        $material->save();
        SharedWorkCatalog::forgetCache();

        $quote->refresh();
        $calcAfter = is_array($quote->calculation) ? $quote->calculation : [];
        $summaryAfter = $calcAfter['summary'] ?? [];

        $this->assertSame(
            $summaryBefore['materials_pre_hst'] ?? null,
            $summaryAfter['materials_pre_hst'] ?? null
        );
        $this->assertSame(
            $summaryBefore['subtotal_pre_hst'] ?? null,
            $summaryAfter['subtotal_pre_hst'] ?? null
        );
    }
}
