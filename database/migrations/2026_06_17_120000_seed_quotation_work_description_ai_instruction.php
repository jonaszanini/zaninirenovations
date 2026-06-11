<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ai_instructions')->where('key', 'quotation_work_description')->exists()) {
            return;
        }

        $instruction = require database_path('data/ai_instruction_quotation_work_description.php');

        DB::table('ai_instructions')->insert([
            'key' => 'quotation_work_description',
            'name' => 'Quotation work description',
            'instruction' => $instruction,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_instructions')->where('key', 'quotation_work_description')->delete();
    }
};
