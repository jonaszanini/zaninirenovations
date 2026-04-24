<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fence_materials', function (Blueprint $table) {
            $table->string('long_name', 500)->nullable()->after('name');
            $table->string('model', 160)->nullable()->after('long_name');
            $table->string('store_sku', 160)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('fence_materials', function (Blueprint $table) {
            $table->dropColumn(['long_name', 'model', 'store_sku']);
        });
    }
};
