<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('painting_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('labor_per_sqf', 10, 4)->default(2.51);
            $table->decimal('paint_per_gallon', 10, 2)->default(55.00);
            $table->decimal('material_addon_fee', 10, 2)->default(100.00);
            $table->decimal('sqf_per_gallon', 10, 2)->default(70.00);
            $table->timestamps();
        });

        Schema::create('painting_paint_lines', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 160);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('painting_quotations', function (Blueprint $table) {
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
            $table->boolean('include_commute')->default(true);
            $table->decimal('commute_trip_percent', 5, 2)->default(100);
            $table->unsignedSmallInteger('commute_estimated_trips')->default(1);
            $table->decimal('commute_round_trip_cost', 10, 2)->nullable();
            $table->decimal('commute_amount', 12, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->index('client_project_id');
        });

        $now = now();
        DB::table('painting_settings')->insert([
            'id' => 1,
            'labor_per_sqf' => 2.51,
            'paint_per_gallon' => 55.00,
            'material_addon_fee' => 100.00,
            'sqf_per_gallon' => 70.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('painting_paint_lines')->insert([
            [
                'key' => 'dulux_lifemaster',
                'name' => 'Dulux Lifemaster',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'benjamin_moore_regal',
                'name' => 'Benjamin Moore Regal Select',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('painting_quotations');
        Schema::dropIfExists('painting_paint_lines');
        Schema::dropIfExists('painting_settings');
    }
};
