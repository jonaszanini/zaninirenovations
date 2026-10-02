<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ai_instructions')->where('key', 'portfolio_categories')->exists()) {
            return;
        }

        $instruction = require database_path('data/ai_instruction_portfolio_categories.php');

        DB::table('ai_instructions')->insert([
            'key' => 'portfolio_categories',
            'name' => 'Portfolio project categories',
            'instruction' => $instruction,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_instructions')->where('key', 'portfolio_categories')->delete();
    }
};
