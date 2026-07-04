<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('url', 500);
            $table->text('notes')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('name');
            $table->index(['is_favorite', 'sort_order']);
        });

        Schema::create('supplier_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('supplier_site_supplier_tag', function (Blueprint $table) {
            $table->foreignId('supplier_site_id')->constrained('supplier_sites')->cascadeOnDelete();
            $table->foreignId('supplier_tag_id')->constrained('supplier_tags')->cascadeOnDelete();

            $table->primary(['supplier_site_id', 'supplier_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_site_supplier_tag');
        Schema::dropIfExists('supplier_tags');
        Schema::dropIfExists('supplier_sites');
    }
};
