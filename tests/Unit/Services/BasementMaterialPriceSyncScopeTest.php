<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Models\Material;
use App\Models\SharedWork;
use App\Services\BasementMaterialPriceSyncScope;
use App\Services\SharedWorkCatalog;
use Tests\IntegrationTestCase;

class BasementMaterialPriceSyncScopeTest extends IntegrationTestCase
{
    public function test_linked_ids_include_basement_shared_work_and_settings_only(): void
    {
        $basementOnly = Material::query()->create([
            'name' => 'Basement scope A',
            'store_sku' => 'BSM-SCOPE-A',
            'price_cad' => 1,
            'is_active' => true,
        ]);
        $trimOnly = Material::query()->create([
            'name' => 'Trim scope B',
            'store_sku' => 'BSM-SCOPE-B',
            'price_cad' => 2,
            'is_active' => true,
        ]);

        $basementWork = SharedWork::query()->where('key', 'underlayment')->firstOrFail();
        $basementWork->catalog_material_id = $basementOnly->id;
        $basementWork->consumers = ['basement', 'flooring'];
        $basementWork->save();

        $trimWork = SharedWork::query()->where('key', 'casing_lf')->firstOrFail();
        $trimWork->catalog_material_id = $trimOnly->id;
        $trimWork->consumers = ['trimming'];
        $trimWork->save();

        $settings = BasementSettings::current();
        $materials = $settings->resolvedMaterials();
        $materials['drywall_compound']['catalog_material_id'] = $basementOnly->id;
        $settings->materials = $materials;
        $settings->save();

        SharedWorkCatalog::forgetCache();

        $ids = app(BasementMaterialPriceSyncScope::class)->linkedCatalogMaterialIds();

        $this->assertContains((int) $basementOnly->id, $ids);
        $this->assertNotContains((int) $trimOnly->id, $ids);
    }
}
