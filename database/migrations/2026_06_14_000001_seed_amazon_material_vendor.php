<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('material_vendors')) {
            return;
        }

        if (DB::table('material_vendors')->where('slug', 'amazon')->exists()) {
            return;
        }

        $now = now();

        DB::table('material_vendors')->insert([
            'name' => 'Amazon',
            'slug' => 'amazon',
            'is_default_for_homedepot' => false,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('material_vendors')) {
            return;
        }

        DB::table('material_vendors')->where('slug', 'amazon')->delete();
    }
};
