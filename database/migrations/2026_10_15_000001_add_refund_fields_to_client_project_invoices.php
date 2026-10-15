<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('payment_method');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refunded_at');
            $table->string('refund_method', 32)->nullable()->after('refund_amount');
            $table->string('refund_note', 500)->nullable()->after('refund_method');
            $table->string('refund_pdf_path', 500)->nullable()->after('receipt_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'refunded_at',
                'refund_amount',
                'refund_method',
                'refund_note',
                'refund_pdf_path',
            ]);
        });
    }
};
