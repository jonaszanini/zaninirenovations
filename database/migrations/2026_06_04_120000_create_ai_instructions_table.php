<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_instructions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->longText('instruction');
            $table->timestamps();
        });

        $contractInstruction = require database_path('data/ai_instruction_contract.php');

        DB::table('ai_instructions')->insert([
            'key' => 'contract',
            'name' => 'Contract generation',
            'instruction' => $contractInstruction,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_instructions');
    }
};
