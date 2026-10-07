<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offcut inventory shared by the lumber/board calculator and millwork
 * sheet optimizer. Leftovers from one job can be reused on the next.
 *
 * Safe on production MySQL: create-if-missing, nullable FKs with
 * nullOnDelete so deleting a project does not wipe leftover stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offcuts')) {
            return;
        }

        Schema::create('offcuts', function (Blueprint $table) {
            $table->id();
            $table->string('material_key', 80)->index();
            $table->decimal('thickness_in', 8, 3)->nullable();
            $table->decimal('width_in', 8, 3)->nullable();
            $table->decimal('length_in', 8, 3);
            $table->unsignedInteger('qty')->default(1);
            $table->string('location', 120)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_from_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();
            $table->foreignId('used_by_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['used_at', 'material_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offcuts');
    }
};
