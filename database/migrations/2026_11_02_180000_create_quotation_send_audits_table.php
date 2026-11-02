<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quotation_send_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();
            $table->foreignId('client_project_quotation_id')
                ->nullable()
                ->constrained('client_project_quotations')
                ->nullOnDelete();
            $table->string('quotation_type', 40)->nullable()->index();
            $table->unsignedBigInteger('quotation_id')->nullable()->index();
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('admin_name', 160)->default('Admin');
            $table->timestamp('server_time')->useCurrent();
            $table->string('recipient_email', 255);
            $table->string('cc_email', 255)->nullable();
            $table->string('subject', 255);
            $table->text('message')->nullable();
            $table->string('quote_version', 20)->default('v1');
            $table->string('pdf_hash', 64)->index();
            $table->string('status', 20)->default('sent')->index(); // sent | failed
            $table->text('error')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['client_project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_send_audits');
    }
};
