<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->decimal('labour_hours_per_day', 6, 2)->default(8)->after('labour_hourly_rate');
        });

        DB::table('standard_quotations')->whereNull('labour_hours_per_day')->update([
            'labour_hours_per_day' => 8,
        ]);
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn('labour_hours_per_day');
        });
    }
};
