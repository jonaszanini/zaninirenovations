<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->decimal('subtotal_override', 12, 2)
                ->nullable()
                ->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->dropColumn('subtotal_override');
        });
    }
};
