<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->timestamp('reminders_paused_at')->nullable()->after('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn('reminders_paused_at');
        });
    }
};
