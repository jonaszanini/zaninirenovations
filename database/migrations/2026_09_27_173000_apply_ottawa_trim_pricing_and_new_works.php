<?php

use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $sort = (int) SharedWork::query()->max('sort_order');

        foreach (SharedWorkCatalog::definitions() as $key => $def) {
            $mat = $def['material'] ?? null;
            $payload = [
                'group_key' => $def['group_key'],
                'label' => $def['label'],
                'unit' => $def['unit'],
                'labor_kind' => $def['labor_kind'],
                'rate' => $def['rate'],
                'flat_rate' => $def['flat_rate'] ?? null,
                'consumers' => $def['consumers'],
            ];
            if (is_array($mat) && $mat !== []) {
                $payload['material_name'] = $mat['name'] ?? null;
                $payload['material_unit'] = $mat['unit'] ?? null;
                $payload['material_unit_price'] = $mat['unit_price'] ?? null;
            }

            $work = SharedWork::query()->where('key', $key)->first();
            if ($work) {
                unset($payload['label']);
                $work->fill($payload);
                $work->save();
                continue;
            }

            $sort++;
            SharedWork::query()->create(array_merge($payload, [
                'key' => $key,
                'sort_order' => $sort,
                'catalog_material_id' => is_array($mat) ? ($mat['catalog_material_id'] ?? null) : null,
            ]));
        }

        SharedWorkCatalog::forgetCache();
    }

    public function down(): void
    {
        SharedWork::query()->whereIn('key', [
            'door_slab_swap',
            'pocket_door',
            'french_door',
            'stool_apron',
            'jamb_extension',
            'window_picture_frame',
            'quarter_round',
            'base_cap',
            'built_up_baseboard',
            'crown',
            'cove',
            'built_up_crown',
            'chair_rail',
            'picture_rail',
            'box_moulding',
            'remove_trim',
            'stain_trim',
            'wainscot_beadboard',
            'wainscot_bnb',
            'wainscot_raised',
            'wainscot_shaker',
        ])->delete();

        SharedWorkCatalog::forgetCache();
    }
};
