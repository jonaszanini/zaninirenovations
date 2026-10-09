<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('millwork_quotations', function (Blueprint $table) {
            $table->unsignedTinyInteger('cut_plan_version')->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('millwork_quotations', function (Blueprint $table) {
            $table->dropColumn('cut_plan_version');
        });
    }
};
