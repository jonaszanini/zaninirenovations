<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_project_helper_payment_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_helper_payment_id')
                ->constrained('client_project_helper_payments')
                ->name('helper_pay_split_fk')
                ->cascadeOnDelete();
            $table->string('payment_method', 32);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('client_project_helper_payment_id', 'helper_pay_split_payment_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_helper_payment_splits');
    }
};
