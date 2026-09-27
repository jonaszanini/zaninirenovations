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
        } catch (Throwable $e) {
        }
        if ($owner <= 0) {
            $owner = 85.0;
        }

        foreach (SharedWork::query()->where('unit', 'sf')->get() as $work) {
            $work->labor_kind = SharedWork::KIND_FLAT;
            $flat = $work->flat_rate !== null ? (float) $work->flat_rate : 0.0;
            $unitsPerHour = (float) $work->rate;

            if ($flat <= 0 && $unitsPerHour > 0) {
                $flat = round($owner / $unitsPerHour, 4);
            }

            $work->flat_rate = $flat > 0 ? $flat : null;
            if ($flat > 0) {
                $work->rate = round($owner / $flat, 4);
            }
            $work->save();
        }

        SharedWorkCatalog::forgetCache();
    }

    public function down(): void
    {
        $owner = 85.0;
        try {
            $owner = (float) SharedCrewRate::current()->owner_hourly_rate;
        } catch (Throwable $e) {
        }
        if ($owner <= 0) {
            $owner = 85.0;
        }

        foreach (SharedWork::query()->where('unit', 'sf')->get() as $work) {
            $flat = $work->flat_rate !== null ? (float) $work->flat_rate : 0.0;
            if ($flat > 0) {
                $work->rate = round($owner / $flat, 4);
            }
            $work->labor_kind = SharedWork::KIND_PER_HOUR;
            $work->save();
        }

        SharedWorkCatalog::forgetCache();
    }
};
