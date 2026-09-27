<?php

use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $baseboard = SharedWork::query()->where('key', 'baseboard')->first();
        if ($baseboard) {
            $consumers = is_array($baseboard->consumers) ? $baseboard->consumers : [];
            if (! in_array('flooring', $consumers, true)) {
                $consumers[] = 'flooring';
                $baseboard->consumers = $consumers;
            }
            if ($baseboard->flat_rate === null) {
                $baseboard->flat_rate = 6.35;
            }
            $baseboard->save();
        }

        if (! SharedWork::query()->where('key', 'baseboard_caulk_paint')->exists()) {
            $def = SharedWorkCatalog::definition('baseboard_caulk_paint');
            $maxSort = (int) SharedWork::query()->max('sort_order');
            SharedWork::query()->create([
                'key' => 'baseboard_caulk_paint',
                'group_key' => $def['group_key'] ?? 'trim',
                'label' => $def['label'] ?? 'Caulk & paint baseboard',
                'unit' => $def['unit'] ?? 'lf',
                'labor_kind' => $def['labor_kind'] ?? SharedWork::KIND_PER_HOUR,
                'rate' => $def['rate'] ?? 30,
                'flat_rate' => $def['flat_rate'] ?? 2.25,
                'consumers' => $def['consumers'] ?? ['flooring'],
                'sort_order' => $maxSort + 1,
            ]);
        }

        SharedWorkCatalog::forgetCache();
    }

    public function down(): void
    {
        SharedWork::query()->where('key', 'baseboard_caulk_paint')->delete();

        $baseboard = SharedWork::query()->where('key', 'baseboard')->first();
        if ($baseboard) {
            $consumers = array_values(array_filter(
                is_array($baseboard->consumers) ? $baseboard->consumers : [],
                fn ($consumer) => $consumer !== 'flooring'
            ));
            $baseboard->consumers = $consumers;
            $baseboard->save();
        }

        SharedWorkCatalog::forgetCache();
    }
};
