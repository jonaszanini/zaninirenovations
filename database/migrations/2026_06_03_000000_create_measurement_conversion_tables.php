<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('measurement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_project_id')
                ->constrained('measurement_projects')
                ->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('source_unit', 20);
            $table->string('source_value', 80);
            // Canonical length stored in millimetres for precise conversions.
            $table->decimal('base_mm', 16, 6);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_rows');
        Schema::dropIfExists('measurement_projects');
    }
};
