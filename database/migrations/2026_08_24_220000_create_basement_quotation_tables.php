<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basement_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('owner_hourly_rate', 10, 2)->default(85.00);
            $table->decimal('helper_hourly_rate', 10, 2)->default(45.00);
            $table->decimal('hours_per_day', 6, 2)->default(8.00);
            $table->json('productivity')->nullable();
            $table->json('materials')->nullable();
            $table->timestamps();
        });

        DB::table('basement_settings')->insert([
            'id' => 1,
            'owner_hourly_rate' => 85.00,
            'helper_hourly_rate' => 45.00,
            'hours_per_day' => 8.00,
            'productivity' => null,
            'materials' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('basement_quotations', function (Blueprint $table) {
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
            $table->unsignedTinyInteger('payment_installments')->nullable();
            $table->json('payment_percentages')->nullable();
            $table->boolean('include_debris_removal')->default(false);
            $table->decimal('debris_removal_amount', 12, 2)->nullable();
            $table->json('additional_costs')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->index('client_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basement_quotations');
        Schema::dropIfExists('basement_settings');
    }
};
