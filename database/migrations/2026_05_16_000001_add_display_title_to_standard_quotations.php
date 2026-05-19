<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->string('display_title', 255)->nullable()->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn('display_title');
        });
    }
};
