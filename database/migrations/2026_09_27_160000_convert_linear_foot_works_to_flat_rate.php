<?php

use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $owner = 85.0;
        try {
            $owner = (float) SharedCrewRate::current()->owner_hourly_rate;
        } catch (\Throwable $e) {
            // Keep the default crew rate when the table is not ready.
        }

        foreach (SharedWork::query()->where('unit', 'lf')->get() as $work) {
            $def = SharedWorkCatalog::definition($work->key) ?? [];
            $work->labor_kind = SharedWork::KIND_FLAT;
            if ($work->flat_rate === null || (float) $work->flat_rate <= 0) {
                $fromDef = (float) ($def['flat_rate'] ?? 0);
                if ($fromDef > 0) {
                    $work->flat_rate = $fromDef;
                } elseif ((float) $work->rate > 0 && $owner > 0) {
                    $work->flat_rate = round($owner / (float) $work->rate, 4);
                }
            }
            if ($work->flat_rate !== null && (float) $work->flat_rate > 0 && $owner > 0) {
                $work->rate = round($owner / (float) $work->flat_rate, 4);
            }
            $work->save();
        }

        SharedWorkCatalog::forgetCache();
    }

    public function down(): void
    {
        foreach (SharedWork::query()->where('unit', 'lf')->get() as $work) {
            $def = SharedWorkCatalog::definition($work->key) ?? [];
            $work->labor_kind = SharedWork::KIND_PER_HOUR;
            if (isset($def['rate'])) {
                $work->rate = (float) $def['rate'];
            }
            $work->save();
        }

        SharedWorkCatalog::forgetCache();
    }
};
