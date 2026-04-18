<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Client info
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_address')->nullable();
            $table->text('notes')->nullable();

            // Lifecycle
            $table->string('status')->default('quote_pending')->index();
            $table->timestamp('status_changed_at')->nullable();

            // Scheduling
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('calendar_email_sent_at')->nullable();

            // Financials
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('payment_plan')->default(1); // 1, 2, 3, or 4

            // Reminder bookkeeping
            $table->timestamp('last_quote_reminder_sent_at')->nullable();
            $table->timestamp('quote_sent_follow_up_due_at')->nullable();
            $table->timestamp('quote_sent_follow_up_sent_at')->nullable();

            $table->timestamps();

            $table->index('status_changed_at');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_projects');
    }
};
