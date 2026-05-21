<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->string('pdf_detail_mode', 16)->default('summary')->after('includes_labour');
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn('pdf_detail_mode');
        });
    }
};
