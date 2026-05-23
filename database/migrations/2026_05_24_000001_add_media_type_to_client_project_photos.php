<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_photos', function (Blueprint $table) {
            $table->string('media_type', 16)->default('image')->after('path');
            $table->string('original_filename', 255)->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_photos', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'original_filename']);
        });
    }
};
