<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ai_instructions')->where('key', 'image_modify_prompt')->exists()) {
            return;
        }

        $instruction = require database_path('data/ai_instruction_image_modify_prompt.php');

        DB::table('ai_instructions')->insert([
            'key' => 'image_modify_prompt',
            'name' => 'Image modify prompt improvement',
            'instruction' => $instruction,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_instructions')->where('key', 'image_modify_prompt')->delete();
    }
};
