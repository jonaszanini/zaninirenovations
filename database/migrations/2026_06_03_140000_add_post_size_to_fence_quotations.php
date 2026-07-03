<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->string('post_size', 10)->default('4x4')->after('fence_height_ft');
        });
    }

    public function down(): void
    {
        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->dropColumn('post_size');
        });
    }
};
