<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incoming Interac e-Transfer notifications captured from Gmail (API)
 * or pasted in admin. Matching to invoices is always a human click —
 * this table never auto-records a payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etransfer_notices', function (Blueprint $table) {
            $table->id();
            $table->timestamp('received_at');
            $table->string('sender_name', 180);
            $table->string('sender_email', 180)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('CAD');
            // Interac confirmation / reference number. Unique when present
            // so the same notice cannot be recorded twice. MySQL allows
            // multiple NULLs, which covers emails that omit a reference.
            $table->string('reference_number', 80)->nullable();
            $table->string('message', 500)->nullable();
            $table->text('raw_text')->nullable();
            // api | manual
            $table->string('source', 20)->default('api');
            // pending | matched | ignored
            $table->string('status', 20)->default('pending');
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('client_project_invoices')
                ->nullOnDelete();
            // Client invoice payments are stored on the invoice row itself
            // (no separate payments table). After matching we store that
            // invoice id here so the notice points at the payment record.
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedSmallInteger('match_score')->nullable();
            $table->timestamps();

            $table->unique('reference_number', 'etransfer_notices_reference_unique');
            $table->index('status');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etransfer_notices');
    }
};
