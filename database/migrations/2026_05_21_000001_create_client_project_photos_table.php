<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_project_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')
                ->constrained('client_projects')
                ->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['client_project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_photos');
    }
};
