<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paving_quotations', function (Blueprint $table) {
            $table->text('work_description')->nullable()->after('client_name');
        });

        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->text('work_description')->nullable()->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('paving_quotations', function (Blueprint $table) {
            $table->dropColumn('work_description');
        });

        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->dropColumn('work_description');
        });
    }
};
