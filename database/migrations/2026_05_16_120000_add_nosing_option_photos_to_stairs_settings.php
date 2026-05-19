<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stairs_settings', function (Blueprint $table) {
            $table->json('nosing_option_photos')->nullable()->after('carpet_removal_per_step');
        });
    }

    public function down(): void
    {
        Schema::table('stairs_settings', function (Blueprint $table) {
            $table->dropColumn('nosing_option_photos');
        });
    }
};
