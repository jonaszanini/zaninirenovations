<?php

use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Services\SharedWorkCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_crew_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('owner_hourly_rate', 8, 2)->default(85);
            $table->decimal('helper_hourly_rate', 8, 2)->default(45);
            $table->decimal('hours_per_day', 6, 2)->default(8);
            $table->timestamps();
        });

        Schema::create('shared_works', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('group_key', 40);
            $table->string('label', 160);
            $table->string('unit', 20);
            $table->string('labor_kind', 24);
            $table->decimal('rate', 10, 4)->default(0);
            $table->decimal('flat_rate', 10, 4)->nullable();
            $table->unsignedBigInteger('catalog_material_id')->nullable();
            $table->string('material_name', 160)->nullable();
            $table->string('material_unit', 40)->nullable();
            $table->decimal('material_unit_price', 10, 4)->nullable();
            $table->json('consumers')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        SharedCrewRate::query()->create([
            'id' => 1,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
        ]);

        $sort = 0;
        foreach (SharedWorkCatalog::definitions() as $key => $def) {
            $sort++;
            $mat = $def['material'] ?? null;
            SharedWork::query()->create([
                'key' => $key,
                'group_key' => $def['group_key'],
                'label' => $def['label'],
                'unit' => $def['unit'],
                'labor_kind' => $def['labor_kind'],
                'rate' => $def['rate'],
                'flat_rate' => $def['flat_rate'] ?? null,
                'catalog_material_id' => $mat['catalog_material_id'] ?? null,
                'material_name' => $mat['name'] ?? null,
                'material_unit' => $mat['unit'] ?? null,
                'material_unit_price' => $mat['unit_price'] ?? null,
                'consumers' => $def['consumers'],
                'sort_order' => $sort,
            ]);
        }

        SharedWorkCatalog::forgetCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_works');
        Schema::dropIfExists('shared_crew_rates');
    }
};
