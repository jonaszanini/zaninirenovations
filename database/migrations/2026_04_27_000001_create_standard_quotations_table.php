<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * standard_quotations — standalone materials + labour quotes.
 *
 * Same architectural role as fence_quotations: saved calculator output,
 * optional link to a client project (single line appended to project
 * quotation with apply_hst = false because the grand total already
 * includes HST on materials).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('standard_quotations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();

            $table->string('quote_number', 40)->unique();
            $table->string('client_name', 160);

            $table->json('materials');

            $table->string('labour_mode', 10)->default('hours'); // hours | days
            $table->decimal('labour_quantity', 10, 2)->default(0);
            $table->decimal('labour_hours', 10, 2)->default(0);
            $table->decimal('labour_hourly_rate', 10, 2)->default(65);

            $table->decimal('materials_subtotal_before_hst', 12, 2)->default(0);
            $table->decimal('materials_hst', 12, 2)->default(0);
            $table->decimal('materials_with_hst', 12, 2)->default(0);
            $table->decimal('labour_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->unsignedInteger('version')->default(1);

            $table->timestamp('linked_at')->nullable();

            $table->timestamps();

            $table->index('client_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_quotations');
    }
};
