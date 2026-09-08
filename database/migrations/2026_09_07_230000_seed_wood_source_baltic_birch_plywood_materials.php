<?php

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialVendor;
use App\Models\MillworkSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @return list<array{
     *   veneer: string,
     *   thickness_label: string,
     *   thickness_in: float,
     *   price: float,
     *   sheet_id: string,
     *   sku: string
     * }>
     */
    private function items(): array
    {
        return [
            [
                'veneer' => 'BB/BB',
                'thickness_label' => '6mm / 1/4"',
                'thickness_in' => 0.25,
                'price' => 56.00,
                'sheet_id' => 'ws_bb_bb_6mm_48x96',
                'sku' => 'BB48-BB-6MM',
            ],
            [
                'veneer' => 'BB/BB',
                'thickness_label' => '12mm / 1/2"',
                'thickness_in' => 0.50,
                'price' => 95.67,
                'sheet_id' => 'ws_bb_bb_12mm_48x96',
                'sku' => 'BB48-BB-12MM',
            ],
            [
                'veneer' => 'BB/BB',
                'thickness_label' => '15mm / 5/8"',
                'thickness_in' => 0.625,
                'price' => 109.38,
                'sheet_id' => 'ws_bb_bb_15mm_48x96',
                'sku' => 'BB48-BB-15MM',
            ],
            [
                'veneer' => 'BB/BB',
                'thickness_label' => '18mm / 3/4"',
                'thickness_in' => 0.75,
                'price' => 129.00,
                'sheet_id' => 'ws_bb_bb_18mm_48x96',
                'sku' => 'BB48-BB-18MM',
            ],
            [
                'veneer' => 'BB/BB',
                'thickness_label' => '24mm / 1"',
                'thickness_in' => 1.00,
                'price' => 183.75,
                'sheet_id' => 'ws_bb_bb_24mm_48x96',
                'sku' => 'BB48-BB-24MM',
            ],
            [
                'veneer' => 'B/BB',
                'thickness_label' => '6mm / 1/4"',
                'thickness_in' => 0.25,
                'price' => 61.60,
                'sheet_id' => 'ws_b_bb_6mm_48x96',
                'sku' => 'BB48-B-6MM',
            ],
            [
                'veneer' => 'B/BB',
                'thickness_label' => '12mm / 1/2"',
                'thickness_in' => 0.50,
                'price' => 115.00,
                'sheet_id' => 'ws_b_bb_12mm_48x96',
                'sku' => 'BB48-B-12MM',
            ],
            [
                'veneer' => 'B/BB',
                'thickness_label' => '18mm / 3/4"',
                'thickness_in' => 0.75,
                'price' => 159.95,
                'sheet_id' => 'ws_b_bb_18mm_48x96',
                'sku' => 'BB48-B-18MM',
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        // 1. Ensure "The Wood Source" vendor exists
        $vendor = MaterialVendor::query()->firstOrCreate(
            ['slug' => 'the-wood-source'],
            [
                'name' => 'The Wood Source',
                'is_default_for_homedepot' => false,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        // 2. Resolve "Lumber" category
        $category = MaterialCategory::query()->where('slug', 'lumber')->first()
            ?? MaterialCategory::query()->firstOrCreate(
                ['slug' => 'lumber'],
                ['name' => 'Lumber', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
            );

        $productUrl = 'https://wood-source.com/product/baltic-birch-plywood-4x8/';
        $imageUrl = 'https://wood-source.com/wp-content/uploads/2023/02/B306018-900x900.jpg';

        $createdMaterialIds = [];

        // 3. Insert or update the 8 materials in Material DB
        foreach ($this->items() as $item) {
            $name = "{$item['veneer']} {$item['thickness_label']} Baltic Birch Plywood – 4’x8′";
            $shortName = "{$item['veneer']} {$item['thickness_label']} Baltic Birch 4x8";
            $description = "Baltic Birch Plywood – 4’x8′. Veneer grade: {$item['veneer']}, Thickness: {$item['thickness_label']}. Cabinet grade plywood (96\" x 48\"). Baltic birch plywood is durable, thanks to its multiple, cross-grain veneers. It has a light and uniform colour with allowance for very small sound tight pin knots.";

            $material = Material::query()->where('product_url', $productUrl)
                ->where('name', $name)
                ->first();

            if (! $material) {
                $material = Material::query()->where('store_sku', $item['sku'])->first();
            }

            $attributes = [
                'name' => $name,
                'short_name' => $shortName,
                'description' => $description,
                'category_id' => $category->id,
                'vendor_id' => $vendor->id,
                'store_sku' => $item['sku'],
                'model' => 'BB48',
                'price_cad' => $item['price'],
                'delivery_cad' => 0.00,
                'product_url' => $productUrl,
                'image_url' => $imageUrl,
                'unit' => 'sheet',
                'quantity_per_package' => 1,
                'is_active' => true,
                'last_scraped_at' => $now,
                'last_scrape_status' => Material::SCRAPE_OK,
                'sort_order' => 0,
            ];

            if ($material) {
                $material->update($attributes);
            } else {
                $material = Material::query()->create($attributes);
            }

            $createdMaterialIds[$item['sheet_id']] = [
                'material' => $material,
                'config' => $item,
            ];
        }

        // 4. Link into MillworkSettings catalog['wood_sheets']
        if (DB::getSchemaBuilder()->hasTable('millwork_settings')) {
            $settings = MillworkSettings::query()->find(1) ?? MillworkSettings::current();
            $catalog = $settings->resolvedCatalog();
            $woodSheets = $catalog[MillworkSettings::SLOT_WOOD_SHEETS] ?? [];

            // Index existing wood sheets by ID
            $indexed = [];
            foreach ($woodSheets as $row) {
                if (is_array($row) && ! empty($row['id'])) {
                    // Clean up placeholder name 'test' if present
                    if ($row['id'] === 'ws_ply_34_cab_48x96' && ($row['name'] ?? '') === 'test') {
                        $row['name'] = '3/4" cabinet-grade plywood 4×8';
                        $row['unit'] = 'sheet';
                    }
                    $indexed[$row['id']] = $row;
                }
            }

            // Insert or update each Baltic Birch sheet
            foreach ($createdMaterialIds as $sheetId => $data) {
                /** @var Material $mat */
                $mat = $data['material'];
                $cfg = $data['config'];

                $indexed[$sheetId] = [
                    'id' => $sheetId,
                    'catalog_material_id' => $mat->id,
                    'name' => $mat->name,
                    'unit' => 'sheet',
                    'unit_price' => round((float) $mat->price_cad, 2),
                    'thickness_in' => $cfg['thickness_in'],
                    'width_in' => 48.0,
                    'length_in' => 96.0,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ];
            }

            $catalog[MillworkSettings::SLOT_WOOD_SHEETS] = array_values($indexed);
            $settings->catalog = $catalog;
            $settings->save();
        }
    }

    public function down(): void
    {
        $sheetIds = array_column($this->items(), 'sheet_id');
        $skus = array_column($this->items(), 'sku');

        if (DB::getSchemaBuilder()->hasTable('millwork_settings')) {
            $settings = MillworkSettings::query()->find(1);
            if ($settings && is_array($settings->catalog)) {
                $catalog = $settings->catalog;
                if (! empty($catalog[MillworkSettings::SLOT_WOOD_SHEETS])) {
                    $catalog[MillworkSettings::SLOT_WOOD_SHEETS] = array_values(array_filter(
                        $catalog[MillworkSettings::SLOT_WOOD_SHEETS],
                        fn ($row) => is_array($row) && ! in_array($row['id'] ?? '', $sheetIds, true)
                    ));
                    $settings->catalog = $catalog;
                    $settings->save();
                }
            }
        }

        Material::query()->whereIn('store_sku', $skus)->delete();
    }
};
