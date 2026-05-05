<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone paving stone quotations — mirrors Zanini_Paving_Calculator_v2.xlsx.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('paving_stone_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('sq_ft_per_stone', 10, 4);
            $table->decimal('price_per_stone', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('paving_material_rates', function (Blueprint $table) {
            $table->id();
            $table->string('rate_key', 64)->unique();
            $table->string('label', 160);
            $table->string('unit', 40)->nullable();
            $table->decimal('price_before_tax', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('paving_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->decimal('area_sq_ft', 12, 2);
            $table->decimal('price_per_sq_ft', 12, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('paving_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();
            $table->string('quote_number', 40)->unique();
            $table->string('client_name', 160)->nullable();
            $table->boolean('is_draft')->default(false);
            $table->json('inputs');
            $table->json('calculation');
            $table->decimal('final_quote_total', 12, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->index('client_project_id');
        });

        $this->seedDefaults();
    }

    protected function seedDefaults(): void
    {
        $now = now();

        DB::table('paving_stone_types')->insert([
            ['name' => 'Charcoal Concrete Block', 'sq_ft_per_stone' => 0.77, 'price_per_stone' => 1.69, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tumbled Limestone', 'sq_ft_per_stone' => 0.5, 'price_per_stone' => 4.50, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Slate Paver', 'sq_ft_per_stone' => 0.67, 'price_per_stone' => 3.25, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Clay Brick', 'sq_ft_per_stone' => 0.44, 'price_per_stone' => 3.00, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Granite Sett', 'sq_ft_per_stone' => 0.25, 'price_per_stone' => 6.00, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $rates = [
            ['rate_key' => 'crushed_stone_cu_yd', 'label' => 'Crushed Stone 3/4"', 'unit' => 'cu yd', 'price_before_tax' => 250.49, 'sort_order' => 1],
            ['rate_key' => 'bedding_sand_cu_yd', 'label' => 'Bedding Sand', 'unit' => 'cu yd', 'price_before_tax' => 140.00, 'sort_order' => 2],
            ['rate_key' => 'polymeric_sand_bag', 'label' => 'Polymeric Sand (Sika FastFix 22.7kg)', 'unit' => 'bag', 'price_before_tax' => 40.00, 'sort_order' => 3],
            ['rate_key' => 'geotextile_roll', 'label' => 'Geotextile Fabric (3×50 ft)', 'unit' => 'roll', 'price_before_tax' => 25.00, 'sort_order' => 4],
            ['rate_key' => 'fabric_staples_box', 'label' => 'Fabric Staples', 'unit' => 'box', 'price_before_tax' => 8.00, 'sort_order' => 5],
            ['rate_key' => 'edge_restraint_piece', 'label' => 'Edge Restraint (ProFlex 6ft)', 'unit' => 'piece', 'price_before_tax' => 12.00, 'sort_order' => 6],
            ['rate_key' => 'edging_spikes_pack', 'label' => 'Edging Spikes', 'unit' => 'pack', 'price_before_tax' => 12.00, 'sort_order' => 7],
            ['rate_key' => 'bin_rental_14yd', 'label' => 'Bin Rental (14-yard)', 'unit' => 'bin', 'price_before_tax' => 455.00, 'sort_order' => 8],
            ['rate_key' => 'disposal_clean_fill_ton', 'label' => 'Disposal Fee (Clean Fill)', 'unit' => 'ton', 'price_before_tax' => 100.00, 'sort_order' => 9],
            ['rate_key' => 'mini_skid_steer_day', 'label' => 'Mini Skid Steer', 'unit' => 'day', 'price_before_tax' => 300.00, 'sort_order' => 10],
            ['rate_key' => 'plate_compactor_day', 'label' => 'Plate Compactor', 'unit' => 'day', 'price_before_tax' => 75.00, 'sort_order' => 11],
        ];
        foreach ($rates as $r) {
            DB::table('paving_material_rates')->insert(array_merge($r, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $tiers = [
            [100, 44.81], [144, 38.01], [150, 37.39], [200, 33.68], [250, 31.45],
            [300, 29.97], [350, 28.91], [400, 28.12], [450, 27.50], [478.7, 27.20],
            [500, 27.00], [550, 26.60], [600, 26.26], [650, 25.97], [687, 25.79],
            [700, 25.73], [750, 25.52], [800, 25.33],
        ];
        $so = 0;
        foreach ($tiers as [$area, $pps]) {
            $so++;
            DB::table('paving_pricing_tiers')->insert([
                'area_sq_ft' => $area,
                'price_per_sq_ft' => $pps,
                'sort_order' => $so,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paving_quotations');
        Schema::dropIfExists('paving_pricing_tiers');
        Schema::dropIfExists('paving_material_rates');
        Schema::dropIfExists('paving_stone_types');
    }
};
