<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_project_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')
                ->constrained('client_projects')
                ->cascadeOnDelete();

            // Rich-text HTML body produced by the TinyMCE editor.
            $table->longText('html_content')->nullable();

            // Relative path inside storage/app to the latest generated PDF
            // (e.g. "contracts/project-12-v3.pdf"). Null until first generated.
            $table->string('pdf_path', 500)->nullable();

            // Last time the contract was emailed to the client. Null = never sent.
            $table->timestamp('sent_at')->nullable();

            // Auto-incremented every time a NEW PDF is generated after the
            // contract has already been sent at least once. Lets us keep an
            // audit trail of versions in the storage path.
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // One contract per project.
            $table->unique('client_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_contracts');
    }
};
