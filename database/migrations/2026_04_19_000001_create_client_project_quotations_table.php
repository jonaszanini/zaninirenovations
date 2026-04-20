<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_project_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')
                ->constrained('client_projects')
                ->cascadeOnDelete();

            // 'quotation' (firm price) | 'estimate' (variable price)
            $table->string('type', 20)->default('quotation');

            // Whether the work includes labour and/or material. Drives the
            // intro phrase printed in the PDF ("To supply material and labour…",
            // "To labour…", "To supply material…").
            $table->boolean('labour')->default(true);
            $table->boolean('material')->default(true);

            // Line items stored as JSON to keep the schema tight. Each item:
            //   { description: string, amount: number }   (amount = pre-HST)
            $table->json('items')->nullable();

            // Human-friendly identifier shown on the PDF and emails.
            // Format: "QUO-2026-0327" or "EST-2026-0327". Unique forever
            // (we never re-use a number, even if the quotation is deleted).
            $table->string('quotation_number', 40)->unique();

            // Relative path inside storage/app to the latest generated PDF
            // (e.g. "quotations/project-12-v3.pdf"). Null until first generated.
            $table->string('pdf_path', 500)->nullable();

            // Last time the quotation was emailed to the client. Null = never sent.
            $table->timestamp('sent_at')->nullable();

            // Auto-incremented every time a NEW PDF is generated after the
            // quotation has already been sent at least once. Lets us keep
            // an audit trail of versions in the storage path.
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // One quotation per project.
            $table->unique('client_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_quotations');
    }
};
