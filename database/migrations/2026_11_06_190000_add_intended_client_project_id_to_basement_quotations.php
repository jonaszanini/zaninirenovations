<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basement_quotations', function (Blueprint $table) {
            $table->foreignId('intended_client_project_id')
                ->nullable()
                ->after('client_project_id')
                ->constrained('client_projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('basement_quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intended_client_project_id');
        });
    }
};
