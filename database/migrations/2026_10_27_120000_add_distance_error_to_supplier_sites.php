<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_sites', function (Blueprint $table) {
            $table->string('distance_error', 500)->nullable()->after('distance_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_sites', function (Blueprint $table) {
            $table->dropColumn('distance_error');
        });
    }
};
