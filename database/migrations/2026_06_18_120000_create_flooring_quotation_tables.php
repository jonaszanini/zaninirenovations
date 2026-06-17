<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flooring_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('remove_carpet_dispose_rate', 10, 4)->default(1.50);
            $table->decimal('rescrew_subfloor_rate', 10, 2)->default(350.00);
            $table->decimal('install_shoe_moulding_rate', 10, 4)->default(3.50);
            $table->decimal('caulk_paint_shoe_moulding_rate', 10, 4)->default(2.25);
            $table->decimal('closet_door_reinstall_rate', 10, 2)->default(75.00);
            $table->timestamps();
        });

        Schema::create('flooring_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 160);
            $table->decimal('installation_price_per_sqft', 10, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('flooring_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();
            $table->string('quote_number', 40)->unique();
            $table->string('client_name', 160)->nullable();
            $table->boolean('is_draft')->default(false);
            $table->json('inputs');
            $table->json('calculation');
            $table->decimal('final_quote_total', 12, 2)->default(0);
            $table->text('work_description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->index('client_project_id');
        });

        $now = now();
        DB::table('flooring_settings')->insert([
            'id' => 1,
            'remove_carpet_dispose_rate' => 1.50,
            'rescrew_subfloor_rate' => 350.00,
            'install_shoe_moulding_rate' => 3.50,
            'caulk_paint_shoe_moulding_rate' => 2.25,
            'closet_door_reinstall_rate' => 75.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('flooring_types')->insert([
            [
                'key' => 'lvp_click',
                'name' => 'LVP Click System',
                'installation_price_per_sqft' => 4.25,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hardwood',
                'name' => 'Hardwood',
                'installation_price_per_sqft' => 6.50,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('flooring_quotations');
        Schema::dropIfExists('flooring_types');
        Schema::dropIfExists('flooring_settings');
    }
};
