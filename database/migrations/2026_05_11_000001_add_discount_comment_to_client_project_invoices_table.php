<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->string('discount_comment', 120)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn('discount_comment');
        });
    }
};
