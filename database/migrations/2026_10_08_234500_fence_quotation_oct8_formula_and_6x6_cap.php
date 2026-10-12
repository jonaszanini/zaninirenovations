<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 6×6 post caps and concrete rates, editable waste, and an admin-editable
 * fence work-description template. 6×6 concrete defaults are higher than
 * 4×4 because the hole is larger — not a silent reuse of the 4×4 bags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->decimal('concrete_per_post_6x6_6ft', 6, 3)->default(2.500)->after('concrete_per_post_8ft');
            $table->decimal('concrete_per_post_6x6_8ft', 6, 3)->default(3.500)->after('concrete_per_post_6x6_6ft');
            $table->unsignedTinyInteger('waste_extra_posts')->default(1)->after('max_post_spacing_ft');
            $table->unsignedTinyInteger('waste_pickets_per_section')->default(1)->after('waste_extra_posts');
            $table->unsignedTinyInteger('waste_extra_rails')->default(3)->after('waste_pickets_per_section');
            $table->unsignedTinyInteger('waste_extra_brackets')->default(6)->after('waste_extra_rails');
            $table->unsignedTinyInteger('waste_extra_caps')->default(1)->after('waste_extra_brackets');
            $table->unsignedTinyInteger('waste_concrete_posts_per_extra_bag')->default(3)->after('waste_extra_caps');
            $table->text('work_description_template')->nullable()->after('waste_concrete_posts_per_extra_bag');
        });

        $now = now();
        DB::table('fence_materials')->updateOrInsert(
            ['key' => 'post_cap_6x6'],
            [
                'name' => '6×6 Post Cap',
                'price_cad' => 0.00,
                'url' => null,
                'quantity_per_box' => null,
                'sort_order' => 95,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('fence_materials')->where('key', 'post_cap_6x6')->delete();

        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->dropColumn([
                'concrete_per_post_6x6_6ft',
                'concrete_per_post_6x6_8ft',
                'waste_extra_posts',
                'waste_pickets_per_section',
                'waste_extra_rails',
                'waste_extra_brackets',
                'waste_extra_caps',
                'waste_concrete_posts_per_extra_bag',
                'work_description_template',
            ]);
        });
    }
};
