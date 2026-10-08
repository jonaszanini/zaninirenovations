<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fromQuote = require database_path('data/ai_instruction_quotation_work_description_from_quote.php');
        $improve = require database_path('data/ai_instruction_quotation_work_description.php');

        DB::table('ai_instructions')->updateOrInsert(
            ['key' => 'quotation_work_description_from_quote'],
            [
                'name' => 'Quotation work description from quote',
                'instruction' => $fromQuote,
                'updated_at' => now(),
            ]
        );

        DB::table('ai_instructions')->updateOrInsert(
            ['key' => 'quotation_work_description'],
            [
                'name' => 'Quotation work description',
                'instruction' => $improve,
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Instruction text refinement only.
    }
};
