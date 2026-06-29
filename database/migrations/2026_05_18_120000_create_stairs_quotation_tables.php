<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stairs renovation quotations + editable material / rate database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stairs_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('stain_per_step', 10, 2)->default(0);
            $table->decimal('clear_coat_per_step', 10, 2)->default(0);
            // Folded in from 2026_05_16_120000, which has an earlier timestamp
            // than this create and therefore cannot rely on the table existing
            // during a fresh or forward migrate.
            $table->json('nosing_option_photos')->nullable();
            $table->timestamps();
        });

        Schema::create('stairs_material_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 160);
            /** included = nosing in material price; choice = vinyl-without extras */
            $table->string('nosing_mode', 16)->default('included');
            $table->decimal('carpet_removal_per_step', 10, 2)->default(0);
            $table->string('photo_path', 500)->nullable();
            /** Per-step prices: no_open/one_open/two_open + stair types + nosing_prices */
            $table->json('pricing');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stairs_quotations', function (Blueprint $table) {
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

        $now = now();
        DB::table('stairs_settings')->insert([
            'id' => 1,
            'stain_per_step' => 0,
            'clear_coat_per_step' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $defaultPricing = [
            'no_open' => [
                'standard' => 0,
                'winder' => 0,
                'double_landing' => 0,
                'single_landing' => 0,
            ],
            'one_open' => [
                'standard' => 0,
                'winder' => 0,
                'double_landing' => 0,
                'single_landing' => 0,
            ],
            'two_open' => [
                'standard' => 0,
            ],
            'nosing_prices' => [
                'luxury_vinyl_flush' => 0,
                'aluminum_drop' => 0,
                'vinyl_nosing' => 0,
                'rubber_nosing' => 0,
            ],
        ];

        $materials = [
            ['key' => 'presswood_oak_veneer', 'name' => 'Presswood Core Oak Veneer', 'nosing_mode' => 'included', 'sort' => 1],
            ['key' => 'vinyl_with_nosing', 'name' => 'Vinyl with nosing', 'nosing_mode' => 'included', 'sort' => 2],
            ['key' => 'vinyl_without_nosing', 'name' => 'Vinyl without nosing', 'nosing_mode' => 'choice', 'sort' => 3],
            ['key' => 'unfinished_solid_oak_tread', 'name' => 'Unfinished Solid Oak Stair Tread', 'nosing_mode' => 'included', 'sort' => 4],
            ['key' => 'unfinished_oak_tread_cape', 'name' => 'Unfinished Oak Stair Tread cape', 'nosing_mode' => 'included', 'sort' => 5],
        ];

        foreach ($materials as $m) {
            DB::table('stairs_material_types')->insert([
                'key' => $m['key'],
                'name' => $m['name'],
                'nosing_mode' => $m['nosing_mode'],
                'carpet_removal_per_step' => 0,
                'photo_path' => null,
                'pricing' => json_encode($defaultPricing),
                'sort_order' => $m['sort'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stairs_quotations');
        Schema::dropIfExists('stairs_material_types');
        Schema::dropIfExists('stairs_settings');
    }
};
