<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tile_quotations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_project_id')->nullable();
            $table->string('quote_number', 60)->unique();
            $table->string('client_name', 160)->nullable();
            $table->boolean('is_draft')->default(false);
            $table->json('inputs')->nullable();
            $table->json('calculation')->nullable();
            $table->decimal('final_quote_total', 14, 2)->default(0);
            $table->text('work_description')->nullable();
            $table->integer('version')->default(1);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->index('client_project_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tile_quotations');
    }
};
