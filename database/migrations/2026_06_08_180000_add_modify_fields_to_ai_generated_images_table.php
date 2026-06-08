<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generated_images', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_generated_images', 'mode')) {
                $table->string('mode', 20)->default('create')->after('prompt');
            }
            if (! Schema::hasColumn('ai_generated_images', 'source_image_path')) {
                $table->string('source_image_path', 500)->nullable()->after('webp_image_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_generated_images', function (Blueprint $table) {
            if (Schema::hasColumn('ai_generated_images', 'source_image_path')) {
                $table->dropColumn('source_image_path');
            }
            if (Schema::hasColumn('ai_generated_images', 'mode')) {
                $table->dropColumn('mode');
            }
        });
    }
};
