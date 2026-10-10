<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_area_kit_seeds', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->timestamp('seeded_at')->useCurrent();
        });

        if (Schema::hasTable('room_area_assemblies')) {
            $names = DB::table('room_area_assemblies')->pluck('name');
            if ($names->isNotEmpty()) {
                DB::table('room_area_kit_seeds')->insertOrIgnore([
                    'key' => 'defaults_v1',
                    'seeded_at' => now(),
                ]);
            }

        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_area_kit_seeds');
    }
};
