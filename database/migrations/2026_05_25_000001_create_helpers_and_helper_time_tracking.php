<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('phone', 20)->nullable();
            $table->string('email', 255);
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('client_project_helper', function (Blueprint $table) {
            $table->foreignId('client_project_id')->constrained('client_projects')->cascadeOnDelete();
            $table->foreignId('helper_id')->constrained('helpers')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['client_project_id', 'helper_id']);
        });

        Schema::create('client_project_helper_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained('client_projects')->cascadeOnDelete();
            $table->foreignId('helper_id')->constrained('helpers')->cascadeOnDelete();
            $table->string('payment_method', 32);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('total_hours', 10, 2);
            $table->decimal('hourly_rate', 10, 2);
            $table->foreignId('client_project_expense_id')->nullable()
                ->constrained('client_project_expenses')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('client_project_helper_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained('client_projects')->cascadeOnDelete();
            $table->foreignId('helper_id')->constrained('helpers')->cascadeOnDelete();
            $table->foreignId('helper_payment_id')->nullable()
                ->constrained('client_project_helper_payments')->nullOnDelete();
            $table->date('occurred_on');
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->decimal('hours', 8, 2)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['client_project_id', 'occurred_on'], 'cp_helper_time_proj_date_idx');
            $table->index(['helper_id', 'helper_payment_id'], 'cp_helper_time_helper_pay_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_helper_time_entries');
        Schema::dropIfExists('client_project_helper_payments');
        Schema::dropIfExists('client_project_helper');
        Schema::dropIfExists('helpers');
    }
};
