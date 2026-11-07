<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $instruction = require database_path('data/ai_instruction_contract.php');

        DB::table('ai_instructions')->updateOrInsert(
            ['key' => 'contract'],
            [
                'name' => 'Contract generation',
                'instruction' => $instruction,
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Instruction text refinement; no destructive rollback.
    }
};
