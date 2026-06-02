<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_calculations', function (Blueprint $table) {
            $table->id();
            // Tool key (e.g. "pillar_spindle") so the table can host saved
            // calculations for every future utility tool.
            $table->string('tool')->index();
            $table->string('name');
            // Inputs + computed snapshot, kept generic for any tool.
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_calculations');
    }
};
