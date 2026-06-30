<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ai_instructions')->where('key', 'material_short_name')->exists()) {
            return;
        }

        $instruction = require database_path('data/ai_instruction_material_short_name.php');

        DB::table('ai_instructions')->insert([
            'key' => 'material_short_name',
            'name' => 'Material short names',
            'instruction' => $instruction,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_instructions')->where('key', 'material_short_name')->delete();
    }
};
