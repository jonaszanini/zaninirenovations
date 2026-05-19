<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carpet removal is one global $/step (like stain / clear coat), not per material type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stairs_settings', function (Blueprint $table) {
            $table->decimal('carpet_removal_per_step', 10, 2)->default(0)->after('clear_coat_per_step');
        });

        $carry = DB::table('stairs_material_types')
            ->orderBy('sort_order')
            ->value('carpet_removal_per_step');

        if ($carry !== null && Schema::hasTable('stairs_settings')) {
            DB::table('stairs_settings')->where('id', 1)->update([
                'carpet_removal_per_step' => round((float) $carry, 2),
            ]);
        }

        Schema::table('stairs_material_types', function (Blueprint $table) {
            $table->dropColumn('carpet_removal_per_step');
        });
    }

    public function down(): void
    {
        Schema::table('stairs_material_types', function (Blueprint $table) {
            $table->decimal('carpet_removal_per_step', 10, 2)->default(0)->after('nosing_mode');
        });

        $carry = DB::table('stairs_settings')->where('id', 1)->value('carpet_removal_per_step');

        if ($carry !== null && Schema::hasTable('stairs_material_types')) {
            DB::table('stairs_material_types')->update([
                'carpet_removal_per_step' => round((float) $carry, 2),
            ]);
        }

        Schema::table('stairs_settings', function (Blueprint $table) {
            $table->dropColumn('carpet_removal_per_step');
        });
    }
};
