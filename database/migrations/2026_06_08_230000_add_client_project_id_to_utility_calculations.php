<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_calculations', function (Blueprint $table) {
            $table->foreignId('client_project_id')
                ->nullable()
                ->after('name')
                ->constrained('client_projects')
                ->nullOnDelete();

            $table->index(['tool', 'client_project_id']);
        });
    }

    public function down(): void
    {
        Schema::table('utility_calculations', function (Blueprint $table) {
            $table->dropForeign(['client_project_id']);
            $table->dropIndex(['tool', 'client_project_id']);
            $table->dropColumn('client_project_id');
        });
    }
};
