<?php

namespace App\Support;

use App\Models\ClientProject;
use App\Models\Material;
use Illuminate\Support\Collection;

/**
 * Option payloads for the admin search-select when picking catalog materials.
 */
final class MaterialCatalogPickerOptions
{
    /**
     * @param  Collection<int, Material>|iterable<Material>  $catalogMaterials
     * @return list<array<string, mixed>>
     */
    public static function fromMaterials(iterable $catalogMaterials): array
    {
        $options = [];

        foreach ($catalogMaterials as $catalog) {
            if (! $catalog instanceof Material) {
                continue;
            }

            $priceLabel = ClientProject::formatMoney((float) $catalog->price_cad);
            $sku = trim((string) ($catalog->store_sku ?? ''));
            $label = $catalog->displayName();
            if ($sku !== '') {
                $label .= ' · '.$sku;
            }
            $label .= ' · '.$priceLabel;

            $options[] = [
                'id' => $catalog->id,
                'label' => $label,
                'name' => $catalog->displayName(),
                'store_name' => $catalog->name,
                'description' => trim((string) ($catalog->description ?? '')),
                'sku' => $sku,
                'model' => trim((string) ($catalog->model ?? '')),
                'vendor' => trim((string) ($catalog->vendorName() ?? '')),
                'price' => number_format((float) $catalog->price_cad, 2, '.', ''),
                'price_label' => $priceLabel,
                'url' => $catalog->storeProductUrl() ?? '',
                'link_vendor' => $catalog->storeLinkVendor(),
                'quantity_per_package' => $catalog->quantity_per_package,
            ];
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function activeCatalog(): array
    {
        $materials = Material::query()
            ->with('vendor')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'description', 'store_sku', 'model', 'price_cad', 'product_url', 'vendor_id', 'quantity_per_package', 'sort_order']);

        return self::fromMaterials($materials);
    }
}
