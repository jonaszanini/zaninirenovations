<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_area_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_area_project_id')
                ->constrained('room_area_projects')
                ->cascadeOnDelete();
            $table->foreignId('standard_quotation_id')
                ->constrained('standard_quotations')
                ->restrictOnDelete();
            $table->unsignedInteger('quote_version');
            $table->string('signer_name', 255);
            $table->timestamp('signed_at');
            $table->foreignId('admin_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->char('snapshot_hash', 64);
            $table->string('pdf_path');
            $table->string('signature_path');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['room_area_project_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_area_signatures');
    }
};
