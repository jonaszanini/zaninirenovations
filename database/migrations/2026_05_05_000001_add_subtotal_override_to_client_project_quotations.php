<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            // No ->after(): total_amount is added in a later migration, so
            // positioning relative to it breaks a fresh migrate. Column order
            // is cosmetic and irrelevant to the application.
            $table->decimal('subtotal_override', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->dropColumn('subtotal_override');
        });
    }
};
