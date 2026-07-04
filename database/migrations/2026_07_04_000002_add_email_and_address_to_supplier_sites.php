<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_sites', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('phone');
            $table->string('address', 500)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_sites', function (Blueprint $table) {
            $table->dropColumn(['email', 'address']);
        });
    }
};
