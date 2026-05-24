<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->decimal('labour_hst', 10, 2)->default(0)->after('labour_total');
        });
    }

    public function down(): void
    {
        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->dropColumn('labour_hst');
        });
    }
};
