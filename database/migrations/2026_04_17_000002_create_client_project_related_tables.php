<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_project_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('label')->nullable();
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->index('client_project_id');
        });

        Schema::create('client_project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['client_project_id', 'occurred_on']);
        });

        Schema::create('client_project_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['client_project_id', 'occurred_on']);
        });

        Schema::create('client_project_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->decimal('hours', 6, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['client_project_id', 'occurred_on']);
        });

        Schema::create('client_project_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['client_project_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_status_history');
        Schema::dropIfExists('client_project_time_entries');
        Schema::dropIfExists('client_project_returns');
        Schema::dropIfExists('client_project_expenses');
        Schema::dropIfExists('client_project_installments');
    }
};
